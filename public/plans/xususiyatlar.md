Bu datalogik model sizning e-commerce platformangizning to'liq ma'lumotlar bazasi strukturasini ko'rsatadi. Asosiy xususiyatlari:

## **Asosiy bloklar:**

### 1. **Foydalanuvchilar va Autentifikatsiya**
- `users` - asosiy foydalanuvchilar jadvali
- `addresses` - foydalanuvchi manzillari
- `cities` - shaharlar va yetkazib berish ma'lumotlari

### 2. **Ko'p tillilik**
- `languages` - mavjud tillar
- `translations` - barcha tarjimalar (polymorphic)

### 3. **Mahsulot katalogi**
- `categories` - kategoriyalar (hierarchy)
- `products` - asosiy mahsulotlar
- `product_images` - mahsulot rasmlari
- `product_variants` - mahsulot variantlari
- `product_variant_attributes` - variant xususiyatlari

### 4. **Xarid jarayoni**
- `carts` - savatcha
- `wishlists` - sevimlilar
- `orders` - buyurtmalar
- `order_items` - buyurtma elementlari

### 5. **Sharhlar tizimi**
- `reviews` - mahsulot sharhlari
- `review_images` - sharh rasmlari
- `review_helpfulness` - sharhlar uchun ovoz berish

### 6. **Marketing va boshqaruv**
- `coupons` - chegirma kuponlari
- `banners` - reklama bannerlari
- `notifications` - bildirishnomalar
- `settings` - tizim sozlamalari

## **Asosiy bog'lanishlar:**

- **One-to-Many**: User → Orders, Product → Reviews
- **Many-to-Many**: User ↔ Products (through Cart, Wishlist)
- **Polymorphic**: Translations (categories, products, cities)
- **Self-referencing**: Categories (parent-child)

Bu model katta hajmdagi e-commerce loyihalar uchun optimallashtirilgan va barcha zamonaviy funksiyalarni qo'llab-quvvatlaydi.