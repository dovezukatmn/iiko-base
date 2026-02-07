"""
API роуты
"""
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List
from database.connection import get_db
from database.models import MenuItem, User

api_router = APIRouter()


# Роуты для меню
@api_router.get("/menu", tags=["menu"])
async def get_menu_items(
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db)
):
    """Получить список элементов меню"""
    items = db.query(MenuItem).filter(MenuItem.is_available == True).offset(skip).limit(limit).all()
    return {"items": items, "total": len(items)}


@api_router.post("/menu", tags=["menu"], status_code=status.HTTP_201_CREATED)
async def create_menu_item(
    name: str,
    description: str = None,
    price: int = 0,
    category: str = None,
    db: Session = Depends(get_db)
):
    """Создать новый элемент меню"""
    item = MenuItem(
        name=name,
        description=description,
        price=price,
        category=category
    )
    db.add(item)
    db.commit()
    db.refresh(item)
    return item


@api_router.get("/menu/{item_id}", tags=["menu"])
async def get_menu_item(item_id: int, db: Session = Depends(get_db)):
    """Получить элемент меню по ID"""
    item = db.query(MenuItem).filter(MenuItem.id == item_id).first()
    if not item:
        raise HTTPException(status_code=404, detail="Элемент не найден")
    return item


# Роуты для пользователей
@api_router.get("/users", tags=["users"])
async def get_users(
    skip: int = 0,
    limit: int = 100,
    db: Session = Depends(get_db)
):
    """Получить список пользователей"""
    users = db.query(User).offset(skip).limit(limit).all()
    return {"users": users, "total": len(users)}
