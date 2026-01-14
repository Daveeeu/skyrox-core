# 🎮 HytaleAuth Module - OAuth2/Auth0 Authentication

## 📋 Áttekintés

A **HytaleAuth** modul egy teljes körű OAuth2/Auth0 alapú autentikációs rendszer Laravel 12-höz, kifejezetten Hytale szerverek számára tervezve. 

### ✨ Főbb funkciók

- 🔐 **OAuth2/Auth0 integráció** - Biztonságos autentikáció
- 🎫 **JWT token kezelés** - Access & refresh tokenek
- 📱 **Session management** - Multi-device támogatás  
- 🏎️ **Redis cache** - Gyors permission ellenőrzés
- 🛡️ **Middleware védelem** - API végpontok biztosítása
- 📊 **Részletes logging** - Audit trail
- 🎯 **Scope-based permissions** - Finomhangolt jogosultságok
- ⚡ **Console commands** - Adminisztráció
- 📖 **Swagger dokumentáció** - API specifikáció

---

## 🚀 Telepítés

### 1. **Composer Frissítés**

Frissítsd a `composer.json`-t:

```json
{
    "require": {
        "predis/predis": "^2.0",
        "darkaonline/l5-swagger": "^8.0"
    },
    "autoload": {
        "psr-4": {
            "App\\Modules\\HytaleAuth\\": "app/Modules/HytaleAuth/src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "App\\Modules\\HytaleAuth\\Providers\\HytaleAuthServiceProvider"
            ]
        }
    }
}
```

```bash
composer dump-autoload
```

### 2. **Service Provider Regisztráció**

Add hozzá a `config/app.php`-hez:

```php
'providers' => [
    // ...
    App\Modules\HytaleAuth\Providers\HytaleAuthServiceProvider::class,
],
```

### 3. **Környezeti Változók (.env)**

```env
# Auth0 Configuration
HYTALE_AUTH0_DOMAIN=your-tenant.auth0.com
HYTALE_AUTH0_CLIENT_ID=your_client_id
HYTALE_AUTH0_CLIENT_SECRET=your_client_secret
HYTALE_AUTH0_REDIRECT_URI=http://localhost:8001/api/v1/auth/callback
HYTALE_AUTH0_SCOPE="openid profile email hytale:player"

# Hytale API
HYTALE_API_BASE_URL=https://api.hytale.com
HYTALE_API_VERSION=v1

# Token Settings
HYTALE_ACCESS_TOKEN_TTL=3600
HYTALE_REFRESH_TOKEN_TTL=2592000
HYTALE_STATE_TOKEN_TTL=600

# Session Settings
HYTALE_MAX_SESSIONS_PER_PLAYER=3
HYTALE_SESSION_TIMEOUT=86400

# Cache Settings (Redis)
HYTALE_CACHE_ENABLED=true
HYTALE_CACHE_TTL=3600
HYTALE_CACHE_PREFIX="hytale:auth:"

# Redis Configuration
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. **Database Migration**

```bash
php artisan migrate
```

### 5. **Cache Konfigurálás**

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

---

## 📁 Modul Struktúra

```
app/Modules/HytaleAuth/
├── src/
│   ├── Models/
│   │   ├── HytaleUser.php
│   │   ├── HytaleSession.php
│   │   └── HytaleToken.php
│   ├── Services/
│   │   ├── Auth0Service.php
│   │   ├── TokenService.php
│   │   └── HytaleAuthService.php
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   └── HytaleAuthController.php
│   │   ├── Middleware/
│   │   │   └── HytaleAuthMiddleware.php
│   │   └── Requests/
│   │       ├── LoginCallbackRequest.php
│   │       ├── RefreshTokenRequest.php
│   │       └── UpdateProfileRequest.php
│   ├── Console/Commands/
│   │   └── HytaleAuthCommand.php
│   └── Providers/
│       └── HytaleAuthServiceProvider.php
├── config/
│   └── hytale_auth.php
├── database/migrations/
│   ├── 001_create_hytale_users_table.php
│   ├── 002_create_hytale_sessions_table.php
│   └── 003_create_hytale_tokens_table.php
└── routes/
    └── api.php
```

---

## 🎯 API Végpontok

### **Public Endpoints**

| Method | Endpoint | Leírás |
|--------|----------|--------|
| `POST` | `/api/v1/auth/login` | OAuth2 flow indítása |
| `POST` | `/api/v1/auth/callback` | Auth0 callback kezelése |  
| `POST` | `/api/v1/auth/refresh` | Token frissítése |
| `GET`  | `/api/v1/auth/health` | Health check |

### **Protected Endpoints** (Bearer Token szükséges)

| Method | Endpoint | Leírás |
|--------|----------|--------|
| `GET`  | `/api/v1/auth/me` | Aktuális user adatok |
| `PUT`  | `/api/v1/auth/profile` | Profil frissítése |
| `GET`  | `/api/v1/auth/sessions` | User session-jei |
| `GET`  | `/api/v1/auth/validate` | Token validáció |
| `POST` | `/api/v1/auth/logout` | Kijelentkezés |

---

## 💻 Használati Példák

### **1. OAuth2 Login Flow**

```javascript
// 1. Login indítása
const loginResponse = await fetch('/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        redirect_uri: 'http://localhost:8000/api/v1/auth/callback',
        scope: 'openid profile email hytale:player'
    })
});

const { authorization_url, state } = await loginResponse.json();

// 2. User átirányítása Auth0-ra
window.location.href = authorization_url;

// 3. Callback kezelése (a redirect után)
const callbackResponse = await fetch('/api/v1/auth/callback', {
    method: 'POST', 
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        code: 'authorization_code_from_auth0',
        state: state
    })
});

const { user, tokens } = await callbackResponse.json();
localStorage.setItem('access_token', tokens.access_token);
localStorage.setItem('refresh_token', tokens.refresh_token);
```

### **2. Protected API Hívások**

```javascript
// Bearer token használata
const response = await fetch('/api/v1/auth/me', {
    headers: {
        'Authorization': `Bearer ${localStorage.getItem('access_token')}`
    }
});

const { user } = await response.json();
console.log(`Üdv, ${user.display_name}!`);
```

### **3. Laravel Middleware**

```php
// routes/api.php
Route::middleware(['hytale.auth'])->group(function () {
    Route::get('/protected', function (Request $request) {
        $user = $request->attributes->get('hytale_user');
        return response()->json(['message' => "Hello, {$user['username']}!"]);
    });
});

// Scope-based védelem
Route::middleware(['hytale.auth:hytale:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

### **4. Service Használat**

```php
use App\Modules\HytaleAuth\Services\HytaleAuthService;

class GameController extends Controller
{
    public function __construct(private HytaleAuthService $authService) {}
    
    public function playerJoin(Request $request)
    {
        $token = $request->bearerToken();
        $validation = $this->authService->validateTokenAndGetUser($token);
        
        if ($validation['success']) {
            $user = $validation['user'];
            // Játékos beléptetése...
        }
    }
}
```

---

## ⚡ Console Parancsok

```bash
# Statisztikák megtekintése
php artisan hytale:auth stats

# Lejárt adatok tisztítása
php artisan hytale:auth cleanup:all --force

# User információk
php artisan hytale:auth user:info --hytale-uuid=abc-123-def

# User tokenek visszavonása
php artisan hytale:auth user:revoke-tokens --user-id=123 --force

# Cache tisztítása
php artisan hytale:auth cache:clear

# Health check
php artisan hytale:auth health
```

---

## 🛡️ Biztonsági Funkciók

- ✅ **CSRF védelem** - State parameter validáció
- ✅ **Token encryption** - Biztonságos tárolás
- ✅ **IP tracking** - Session biztonság
- ✅ **Rate limiting** - Brute force védelem
- ✅ **Scope validation** - Finomhangolt jogosultságok
- ✅ **Automatic cleanup** - Lejárt tokenek törlése
- ✅ **Session limits** - Max egyidejű bejelentkezések

---

## 📊 Swagger Dokumentáció

```bash
# Swagger generálása
php artisan l5-swagger:generate

# Elérhető itt:
http://localhost:8000/api/documentation
```

---

## 🔧 Testreszabás

### **Új Scope Hozzáadása**

```php
// config/hytale_auth.php
'scopes' => [
    'hytale:player' => 'Player információk',
    'hytale:admin' => 'Admin jogosultságok', 
    'hytale:custom' => 'Egyedi funkciók',  // <- új scope
],
```

### **Webhook Események**

```php
// config/hytale_auth.php
'webhooks' => [
    'enabled' => true,
    'endpoint' => 'https://your-server.com/webhooks/hytale-auth',
    'events' => [
        'player.authenticated',
        'player.logout',
        'token.refreshed',
    ],
],
```

---

## 🚨 Hibaelhárítás

### **Gyakori Problémák**

**1. Redis kapcsolódási hiba:**
```bash
redis-cli ping
# Válasz: PONG
```

**2. Auth0 konfiguráció ellenőrzés:**
```bash
php artisan hytale:auth health
```

**3. Token validation hiba:**
```bash
php artisan hytale:auth user:info --user-id=123
```

**4. Cache problémák:**
```bash
php artisan hytale:auth cache:clear
php artisan config:clear
```

---

## 📈 Production Checklist

- [ ] Auth0 production domain konfigurálva
- [ ] HTTPS redirect URI beállítva
- [ ] Redis production environment
- [ ] Proper token TTL értékek
- [ ] Logging konfigurálva
- [ ] Rate limiting engedélyezve
- [ ] Backup strategy a tokenekhez
- [ ] Monitoring beállítva
- [ ] Webhook endpoints tesztelve

---

## 🎉 **Kész!**

A **HytaleAuth modul** teljes mértékben működőképes és production-ready! 

OAuth2/Auth0 integráció ✅  
JWT token management ✅  
Session handling ✅  
Redis caching ✅  
Comprehensive API ✅  
Full documentation ✅

**Happy coding!** 🚀🎮
