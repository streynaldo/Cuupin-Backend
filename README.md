# 🥐 Cuupin Backend

A Laravel-based backend API for managing bakery flash-sale and pre-order systems — enabling customers to reserve limited bakery items, manage transactions, and help bakeries reduce waste through time-based discount sessions.

---

## 📘 About the Project

**Cuupin Backend** is a backend system built with **Laravel**, developed to support a bakery flash-sale and reservation platform.  
It allows users to pre-order or “cup” bakery items before they’re sold out, while helping bakeries manage inventory and reduce waste through smart scheduling and dynamic discounting.

The system handles all core backend operations such as **user authentication**, **product and order management**, **payment integration**, and **merchant analytics** — providing a smooth and scalable backend foundation for the Cuupin ecosystem.

---

## ✨ Features

- 🥖 **Flash Sale & Pre-Order System** — Users can reserve limited bakery items during time-based discount sessions.  
- 🧁 **Merchant Dashboard Support** — Designed to integrate with bakery-side dashboards for product and sales management.  
- 💳 **Secure Payment Handling** — Supports e-wallet payment sessions (OVO, DANA, ShopeePay) through third-party payment gateways.  
- 👥 **Role-Based Access** — Separate authentication flow for customers and bakeries.  
- 📦 **Order Management** — Track, cancel, and fulfill bakery orders efficiently.  
- 🔐 **Authentication & Authorization** — Token-based authentication using **Laravel Sanctum**.  
- 🌐 **API Ready for Mobile Clients** — Optimized for iOS and Android apps with RESTful structure.

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-------------|
| **Framework** | Laravel 11 |
| **Language** | PHP 8.3 |
| **Database** | MySQL |
| **Authentication** | Laravel Sanctum |
| **Payment Gateway** | Xendit |
| **Deployment** | Hostinger VPS + GitHub Actions |
| **Frontend Integration** | Cuupin iOS / Merchant App |

---

## ⚙️ Installation

### 1️⃣ Clone Repository
```bash
git clone https://github.com/streynaldo/Cuupin-Backend.git
cd cuupin-backend
composer install
cp .env.example .env and edit it
    APP_NAME="Cuupin Backend"
    DB_DATABASE=cuupin
    DB_USERNAME=root
    DB_PASSWORD=
    
    # Xendit Configuration
    XENDIT_API_KEY=your_xendit_api_key
    XENDIT_CALLBACK_TOKEN=your_callback_token
php artisan migrate
php artisan serve


