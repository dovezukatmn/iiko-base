"""
Модуль синхронизации данных из iiko Cloud API
Comprehensive synchronization module for iiko Cloud API integration
Based on official iiko Cloud API documentation
"""
import logging
import time
from typing import Optional, Dict, List, Any
from datetime import datetime
from sqlalchemy.orm import Session
from sqlalchemy import text

from app.iiko_service import IikoService
from database.models import IikoSettings

logger = logging.getLogger(__name__)


class IikoSyncService:
    """
    Сервис синхронизации данных из iiko Cloud API в локальную БД
    
    Синхронизирует:
    - Категории меню (groups)
    - Товары/блюда (items)
    - Модификаторы (modifiers)
    - Комбо (combo)
    - Стоп-листы (out_of_stock)
    - Терминалы (terminal_groups)
    - Типы оплат (payment_types)
    - Ценовые категории (price_categories)
    - Внешнее меню (external_menus)
    """

    def __init__(self, db: Session, iiko_settings: Optional[IikoSettings] = None):
        self.db = db
        self.iiko_service = IikoService(db, iiko_settings)
        self.iiko_settings = iiko_settings

    async def sync_all(self, organization_id: str) -> Dict[str, Any]:
        """
        Полная синхронизация всех данных из iiko
        
        Returns:
            Dict with sync results for each entity type
        """
        start_time = time.time()
        results = {}
        
        logger.info(f"Starting full sync for organization {organization_id}")
        
        try:
            # Authenticate first
            await self.iiko_service.authenticate()
            
            # Sync in recommended order
            results['categories'] = await self.sync_categories(organization_id)
            results['products'] = await self.sync_products(organization_id)
            results['modifiers'] = await self.sync_modifiers(organization_id)
            results['terminal_groups'] = await self.sync_terminal_groups([organization_id])
            results['payment_types'] = await self.sync_payment_types([organization_id])
            results['stop_lists'] = await self.sync_stop_lists(organization_id)
            # results['combos'] = await self.sync_combos(organization_id)  # Optional
            
            duration_ms = int((time.time() - start_time) * 1000)
            
            # Log sync history
            self._log_sync_history(
                organization_id=organization_id,
                sync_type='full',
                status='success',
                items_synced=sum(r.get('synced', 0) for r in results.values() if isinstance(r, dict)),
                duration_ms=duration_ms
            )
            
            logger.info(f"Full sync completed in {duration_ms}ms")
            return {
                'status': 'success',
                'duration_ms': duration_ms,
                'results': results
            }
            
        except Exception as e:
            duration_ms = int((time.time() - start_time) * 1000)
            error_msg = str(e)
            logger.error(f"Full sync failed: {error_msg}")
            
            self._log_sync_history(
                organization_id=organization_id,
                sync_type='full',
                status='failed',
                error_message=error_msg,
                duration_ms=duration_ms
            )
            
            return {
                'status': 'error',
                'error': error_msg,
                'duration_ms': duration_ms,
                'results': results
            }

    async def sync_categories(self, organization_id: str) -> Dict[str, Any]:
        """
        Синхронизация категорий меню (groups из nomenclature)
        """
        start_time = time.time()
        try:
            # Get nomenclature from iiko
            nom_data = await self.iiko_service.get_menu(organization_id)
            groups = nom_data.get('groups', [])
            
            synced_count = 0
            for group in groups:
                self._upsert_category(group)
                synced_count += 1
            
            self.db.commit()
            
            duration_ms = int((time.time() - start_time) * 1000)
            logger.info(f"Synced {synced_count} categories in {duration_ms}ms")
            
            return {
                'status': 'success',
                'synced': synced_count,
                'duration_ms': duration_ms
            }
            
        except Exception as e:
            self.db.rollback()
            logger.error(f"Failed to sync categories: {e}")
            return {
                'status': 'error',
                'error': str(e),
                'synced': 0
            }

    async def sync_products(self, organization_id: str) -> Dict[str, Any]:
        """
        Синхронизация товаров/блюд (items из nomenclature)
        """
        start_time = time.time()
        try:
            nom_data = await self.iiko_service.get_menu(organization_id)
            items = nom_data.get('products', [])
            
            synced_count = 0
            for item in items:
                self._upsert_product(item)
                synced_count += 1
            
            self.db.commit()
            
            duration_ms = int((time.time() - start_time) * 1000)
            logger.info(f"Synced {synced_count} products in {duration_ms}ms")
            
            return {
                'status': 'success',
                'synced': synced_count,
                'duration_ms': duration_ms
            }
            
        except Exception as e:
            self.db.rollback()
            logger.error(f"Failed to sync products: {e}")
            return {
                'status': 'error',
                'error': str(e),
                'synced': 0
            }

    async def sync_modifiers(self, organization_id: str) -> Dict[str, Any]:
        """
        Синхронизация модификаторов (modifiers из nomenclature)
        """
        start_time = time.time()
        try:
            nom_data = await self.iiko_service.get_menu(organization_id)
            
            # Sync modifier groups
            groups = nom_data.get('groups', [])
            groups_count = 0
            for group in groups:
                if 'modifierSchema' in group:
                    self._upsert_modifier_group(group)
                    groups_count += 1
            
            # Sync individual modifiers
            modifiers = nom_data.get('modifiers', [])
            mods_count = 0
            for modifier in modifiers:
                self._upsert_modifier(modifier)
                mods_count += 1
            
            self.db.commit()
            
            duration_ms = int((time.time() - start_time) * 1000)
            total_synced = groups_count + mods_count
            logger.info(f"Synced {groups_count} modifier groups and {mods_count} modifiers in {duration_ms}ms")
            
            return {
                'status': 'success',
                'synced': total_synced,
                'modifier_groups': groups_count,
                'modifiers': mods_count,
                'duration_ms': duration_ms
            }
            
        except Exception as e:
            self.db.rollback()
            logger.error(f"Failed to sync modifiers: {e}")
            return {
                'status': 'error',
                'error': str(e),
                'synced': 0
            }

    async def sync_stop_lists(self, organization_id: str) -> Dict[str, Any]:
        """
        Синхронизация стоп-листов (out_of_stock)
        """
        start_time = time.time()
        try:
            # Get stop list from iiko
            stop_data = await self.iiko_service.get_stop_lists(organization_id)
            
            # First, mark all products as available for this organization
            # We need to track which products belong to this org somehow,
            # or we mark all available and then set specific ones to unavailable
            self.db.execute(text("""
                UPDATE products 
                SET is_available = TRUE 
                WHERE id IN (
                    SELECT DISTINCT product_id 
                    FROM stop_lists 
                    WHERE organization_id = :org_id
                )
            """), {'org_id': organization_id})
            
            # Then mark stopped items as unavailable
            synced_count = 0
            terminal_groups = stop_data.get('terminalGroupOutOfStockItems', [])
            
            for tg in terminal_groups:
                terminal_group_id = tg.get('terminalGroupId')
                items = tg.get('items', [])
                
                for item in items:
                    iiko_product_id = item.get('productId')
                    balance = item.get('balance', 0)
                    
                    # Update product availability by iiko_id
                    self.db.execute(
                        text("UPDATE products SET is_available = FALSE WHERE iiko_id = :iiko_product_id"),
                        {'iiko_product_id': iiko_product_id}
                    )
                    
                    # Get the local product id from iiko_id for foreign key
                    result = self.db.execute(
                        text("SELECT id FROM products WHERE iiko_id = :iiko_product_id LIMIT 1"),
                        {'iiko_product_id': iiko_product_id}
                    )
                    row = result.fetchone()
                    if row:
                        product_id = row[0]
                        
                        # Upsert stop_list entry
                        self.db.execute(
                            text("""
                                INSERT INTO stop_lists (organization_id, terminal_group_id, product_id, balance, is_stopped)
                                VALUES (:org_id, :tg_id, :product_id, :balance, TRUE)
                                ON CONFLICT (organization_id, terminal_group_id, product_id)
                                DO UPDATE SET balance = :balance, is_stopped = TRUE, updated_at = CURRENT_TIMESTAMP
                            """),
                            {
                                'org_id': organization_id,
                                'tg_id': terminal_group_id,
                                'product_id': product_id,
                                'balance': balance
                            }
                        )
                        synced_count += 1
            
            self.db.commit()
            
            duration_ms = int((time.time() - start_time) * 1000)
            logger.info(f"Synced {synced_count} stop list items in {duration_ms}ms")
            
            return {
                'status': 'success',
                'synced': synced_count,
                'duration_ms': duration_ms
            }
            
        except Exception as e:
            self.db.rollback()
            logger.error(f"Failed to sync stop lists: {e}")
            return {
                'status': 'error',
                'error': str(e),
                'synced': 0
            }

    async def sync_terminal_groups(self, organization_ids: List[str]) -> Dict[str, Any]:
        """
        Синхронизация терминальных групп
        """
        start_time = time.time()
        try:
            tg_data = await self.iiko_service.get_terminal_groups(organization_ids)
            terminal_groups = tg_data.get('terminalGroups', [])
            
            synced_count = 0
            for tg in terminal_groups:
                self._upsert_terminal_group(tg)
                synced_count += 1
            
            self.db.commit()
            
            duration_ms = int((time.time() - start_time) * 1000)
            logger.info(f"Synced {synced_count} terminal groups in {duration_ms}ms")
            
            return {
                'status': 'success',
                'synced': synced_count,
                'duration_ms': duration_ms
            }
            
        except Exception as e:
            self.db.rollback()
            logger.error(f"Failed to sync terminal groups: {e}")
            return {
                'status': 'error',
                'error': str(e),
                'synced': 0
            }

    async def sync_payment_types(self, organization_ids: List[str]) -> Dict[str, Any]:
        """
        Синхронизация типов оплат
        """
        start_time = time.time()
        try:
            pt_data = await self.iiko_service.get_payment_types(organization_ids)
            payment_types = pt_data.get('paymentTypes', [])
            
            synced_count = 0
            for pt in payment_types:
                self._upsert_payment_type(pt)
                synced_count += 1
            
            self.db.commit()
            
            duration_ms = int((time.time() - start_time) * 1000)
            logger.info(f"Synced {synced_count} payment types in {duration_ms}ms")
            
            return {
                'status': 'success',
                'synced': synced_count,
                'duration_ms': duration_ms
            }
            
        except Exception as e:
            self.db.rollback()
            logger.error(f"Failed to sync payment types: {e}")
            return {
                'status': 'error',
                'error': str(e),
                'synced': 0
            }

    # =========================================================================
    # Helper methods for upserting data
    # =========================================================================

    def _upsert_category(self, group: Dict[str, Any]):
        """Upsert category from iiko group"""
        iiko_id = group.get('id')
        if not iiko_id:
            return
        
        self.db.execute(
            text("""
                INSERT INTO categories (id, iiko_id, parent_id, name, description, sort_order, synced_at)
                VALUES (:id, :iiko_id, :parent_id, :name, :description, :sort_order, CURRENT_TIMESTAMP)
                ON CONFLICT (id)
                DO UPDATE SET
                    name = :name,
                    description = :description,
                    parent_id = :parent_id,
                    sort_order = :sort_order,
                    synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
            """),
            {
                'id': iiko_id,
                'iiko_id': iiko_id,
                'parent_id': group.get('parentGroup'),
                'name': group.get('name', ''),
                'description': group.get('description'),
                'sort_order': group.get('order', 0)
            }
        )

    def _upsert_product(self, item: Dict[str, Any]):
        """Upsert product from iiko item"""
        iiko_id = item.get('id')
        if not iiko_id:
            return
        
        # Extract nutrition info if available
        nutrition = item.get('nutritionPerHundredGrams', {})
        
        self.db.execute(
            text("""
                INSERT INTO products (
                    id, iiko_id, category_id, parent_group, name, description,
                    code, weight, measure_unit, price, energy_value, fats, proteins, carbs, synced_at
                )
                VALUES (
                    :id, :iiko_id, :category_id, :parent_group, :name, :description,
                    :code, :weight, :measure_unit, :price, :energy_value, :fats, :proteins, :carbs, CURRENT_TIMESTAMP
                )
                ON CONFLICT (id)
                DO UPDATE SET
                    name = :name,
                    description = :description,
                    category_id = :category_id,
                    parent_group = :parent_group,
                    code = :code,
                    weight = :weight,
                    measure_unit = :measure_unit,
                    price = :price,
                    energy_value = :energy_value,
                    fats = :fats,
                    proteins = :proteins,
                    carbs = :carbs,
                    synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
            """),
            {
                'id': iiko_id,
                'iiko_id': iiko_id,
                'category_id': item.get('parentGroup'),
                'parent_group': item.get('parentGroup'),
                'name': item.get('name', ''),
                'description': item.get('description'),
                'code': item.get('code'),
                'weight': item.get('weight'),
                'measure_unit': item.get('measureUnit'),
                'price': int(item.get('price', 0) * 100) if item.get('price') else 0,  # Convert to kopecks
                'energy_value': nutrition.get('energyFullValue'),
                'fats': nutrition.get('fatFullValue'),
                'proteins': nutrition.get('proteinsFullValue'),
                'carbs': nutrition.get('carbohydratesFullValue')
            }
        )

    def _upsert_modifier_group(self, group: Dict[str, Any]):
        """Upsert modifier group"""
        modifier_schema = group.get('modifierSchema', {})
        if not modifier_schema:
            return
        
        iiko_id = modifier_schema.get('id')
        if not iiko_id:
            return
        
        self.db.execute(
            text("""
                INSERT INTO modifier_groups (id, iiko_id, name, min_amount, max_amount, synced_at)
                VALUES (:id, :iiko_id, :name, :min_amount, :max_amount, CURRENT_TIMESTAMP)
                ON CONFLICT (id)
                DO UPDATE SET
                    name = :name,
                    min_amount = :min_amount,
                    max_amount = :max_amount,
                    synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
            """),
            {
                'id': iiko_id,
                'iiko_id': iiko_id,
                'name': modifier_schema.get('name', ''),
                'min_amount': modifier_schema.get('minAmount', 0),
                'max_amount': modifier_schema.get('maxAmount', 1)
            }
        )

    def _upsert_modifier(self, modifier: Dict[str, Any]):
        """Upsert individual modifier"""
        iiko_id = modifier.get('id')
        if not iiko_id:
            return
        
        self.db.execute(
            text("""
                INSERT INTO modifiers (id, iiko_id, group_id, name, price, synced_at)
                VALUES (:id, :iiko_id, :group_id, :name, :price, CURRENT_TIMESTAMP)
                ON CONFLICT (id)
                DO UPDATE SET
                    name = :name,
                    group_id = :group_id,
                    price = :price,
                    synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
            """),
            {
                'id': iiko_id,
                'iiko_id': iiko_id,
                'group_id': modifier.get('groupId'),
                'name': modifier.get('name', ''),
                'price': int(modifier.get('price', 0) * 100) if modifier.get('price') else 0
            }
        )

    def _upsert_terminal_group(self, tg: Dict[str, Any]):
        """Upsert terminal group"""
        iiko_id = tg.get('id')
        if not iiko_id:
            return
        
        # Get organization ID from items
        org_id = tg.get('organizationId')
        
        self.db.execute(
            text("""
                INSERT INTO terminal_groups (id, iiko_id, organization_id, name, address, synced_at)
                VALUES (:id, :iiko_id, :org_id, :name, :address, CURRENT_TIMESTAMP)
                ON CONFLICT (id)
                DO UPDATE SET
                    name = :name,
                    organization_id = :org_id,
                    address = :address,
                    synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
            """),
            {
                'id': iiko_id,
                'iiko_id': iiko_id,
                'org_id': org_id,
                'name': tg.get('name', ''),
                'address': tg.get('address')
            }
        )

    def _upsert_payment_type(self, pt: Dict[str, Any]):
        """Upsert payment type"""
        iiko_id = pt.get('id')
        if not iiko_id:
            return
        
        # Get organization ID from items
        org_id = pt.get('organizationId')
        
        self.db.execute(
            text("""
                INSERT INTO payment_types (
                    id, iiko_id, organization_id, code, name, comment,
                    is_deleted, print_cheque, synced_at
                )
                VALUES (
                    :id, :iiko_id, :org_id, :code, :name, :comment,
                    :is_deleted, :print_cheque, CURRENT_TIMESTAMP
                )
                ON CONFLICT (id)
                DO UPDATE SET
                    name = :name,
                    code = :code,
                    comment = :comment,
                    is_deleted = :is_deleted,
                    print_cheque = :print_cheque,
                    synced_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
            """),
            {
                'id': iiko_id,
                'iiko_id': iiko_id,
                'org_id': org_id,
                'code': pt.get('code'),
                'name': pt.get('name', ''),
                'comment': pt.get('comment'),
                'is_deleted': pt.get('isDeleted', False),
                'print_cheque': pt.get('printCheque', True)
            }
        )

    def _log_sync_history(self, organization_id: str, sync_type: str, status: str,
                          items_synced: int = 0, error_message: str = None,
                          duration_ms: int = 0):
        """Log sync operation to history"""
        try:
            self.db.execute(
                text("""
                    INSERT INTO sync_history (
                        organization_id, sync_type, status, items_synced,
                        error_message, duration_ms, completed_at
                    )
                    VALUES (
                        :org_id, :sync_type, :status, :items_synced,
                        :error_message, :duration_ms, CURRENT_TIMESTAMP
                    )
                """),
                {
                    'org_id': organization_id,
                    'sync_type': sync_type,
                    'status': status,
                    'items_synced': items_synced,
                    'error_message': error_message,
                    'duration_ms': duration_ms
                }
            )
            self.db.commit()
        except Exception as e:
            logger.error(f"Failed to log sync history: {e}")
