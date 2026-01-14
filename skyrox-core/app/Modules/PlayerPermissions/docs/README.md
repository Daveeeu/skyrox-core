# Hytale Player Permission Module

## Bevezetés

Ez a modul egy komplex jogosultság kezelő rendszer Laravel 12 API projektekhez, kifejezetten Hytale játékszerver integrációkhoz tervezve. A rendszer Redis cache-t használ a gyors adateléréshez és teljes Swagger API dokumentációval rendelkezik.

## Főbb funkciók

- ✅ **Játékos bejelentkezés/kijelentkezés kezelés**
- ✅ **Role-based jogosultság rendszer**
- ✅ **Redis cache integráció**
- ✅ **Session tracking**
- ✅ **Real-time online player lista**
- ✅ **Swagger API dokumentáció**
- ✅ **Console parancsok**
- ✅ **Middleware támogatás**

## Telepítés

### 1. Migration-ök futtatása

```bash
php artisan migrate
```

### 2. Seeder futtatása

```bash
php artisan db:seed --class=PlayerPermissionSeeder
```

### 3. Config közzététele

```bash
php artisan vendor:publish --tag=player-permission-config
```

### 4. Redis konfiguráció

Állítsd be a `.env` fájlban:

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Player Permission specifikus beállítások
PLAYER_PERMISSION_REDIS_ENABLED=true
PLAYER_PERMISSION_REDIS_TTL=3600
PLAYER_PERMISSION_REDIS_PREFIX=hytale:player:
```

## API Végpontok

### Játékos bejelentkezés
```http
POST /api/v1/player/login
Content-Type: application/json

{
    "user_id": 1,
    "server_name": "main-server",
    "ip_address": "192.168.1.1"
}
```

**Válasz:**
```json
{
    "success": true,
    "role_name": "admin",
    "message": "Sikeres bejelentkezés! Üdvözöljük, Player1!",
    "session_id": "uuid-string",
    "permissions": ["game.build", "game.fly", "admin.console"]
}
```

### Játékos kijelentkezés
```http
POST /api/v1/player/logout
Content-Type: application/json

{
    "user_id": 1
}
```

### Permission ellenőrzés
```http
GET /api/v1/player/permission/check?user_id=1&permission=game.build
```

**Válasz:**
```json
{
    "success": true,
    "has_permission": true,
    "message": "Jogosultság megvan.",
    "source": "cache"
}
```

### Online játékosok listája
```http
GET /api/v1/player/online
```

### Játékos részletes adatai
```http
GET /api/v1/player/{userId}/details
```

## Használat a Laravel projektben

### User model kibővítése

```php
// app/Models/User.php
use App\Traits\HasPermissions;

class User extends Authenticatable
{
    use HasPermissions;
    
    // ... többi kód
}
```

### Service használata

```php
use App\Modules\PlayerPermissions\Services\PlayerPermissionService;

class YourController extends Controller
{
    public function __construct(
        private PlayerPermissionService $permissionService
    ) {}
    
    public function someMethod()
    {
        $result = $this->permissionService->playerLogin(1, 'main-server');
        
        if ($result['success']) {
            // Sikeres bejelentkezés
        }
    }
}
```

### Middleware használata

```php
// routes/api.php
Route::get('/protected-endpoint', [SomeController::class, 'method'])
    ->middleware('check.player.permission:admin.console');
```

## Console parancsok

```bash
# Cache törlése
php artisan player-permission cache:clear

# Cache újraépítése
php artisan player-permission cache:rebuild

# Role hozzáadása felhasználóhoz
php artisan player-permission user:assign-role --user-id=1 --role=admin

# Permission tesztelése
php artisan player-permission test:permission --user-id=1 --permission=game.build

# Online játékosok listája
php artisan player-permission online:list

# Statisztikák
php artisan player-permission stats
```

## Jogosultságok és Role-ok

### Alapértelmezett Role-ok

- **guest** - Alapértelmezett vendég
- **player** - Alap játékos
- **vip** - VIP játékos  
- **donor** - Támogató
- **helper** - Segítő moderátor
- **moderator** - Moderátor
- **admin** - Adminisztrátor
- **owner** - Tulajdonos

### Permission kategóriák

- **basic** - Alapvető jogosultságok (join, chat)
- **building** - Építési jogosultságok (build)
- **combat** - Harc jogosultságok (pvp)
- **moderation** - Moderációs jogosultságok (kick, ban, mute)
- **admin** - Admin jogosultságok (teleport, fly, god)
- **special** - Speciális jogosultságok (vip, donor)

## Redis adatstruktúra

```
hytale:player:user:1 -> {
    "user_id": 1,
    "username": "Player1",
    "roles": ["admin"],
    "permissions": ["game.build", "admin.fly"],
    "is_online": true,
    "last_updated": "2024-01-14T10:00:00Z"
}

hytale:player:online_players -> Set(1, 2, 3)
```

## Konfigurációs lehetőségek

```php
// config/player_permission.php

'redis' => [
    'enabled' => true,
    'ttl' => 3600, // 1 óra
    'prefix' => 'hytale:player:',
],

'session' => [
    'auto_logout_inactive' => true,
    'inactive_timeout' => 1800, // 30 perc
    'max_sessions_per_user' => 1,
],

'permissions' => [
    'default_role' => 'guest',
    'cache_permissions' => true,
    'enable_wildcard' => true, // admin.*
],
```

## Hibakeresés

### Log fájlok ellenőrzése

```bash
tail -f storage/logs/laravel.log
```

### Redis kapcsolat tesztelése

```bash
redis-cli ping
```

### Cache állapot ellenőrzése

```bash
php artisan player-permission cache:clear
php artisan player-permission cache:rebuild
```

## API tesztelés

### cURL példák

```bash
# Bejelentkezés
curl -X POST http://localhost:8000/api/v1/player/login \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "server_name": "test-server"}'

# Permission ellenőrzés
curl "http://localhost:8000/api/v1/player/permission/check?user_id=1&permission=game.build"

# Online játékosok
curl "http://localhost:8000/api/v1/player/online"
```

## Swagger dokumentáció

A teljes API dokumentáció elérhető a következő URL-en:
```
http://your-domain.com/api/documentation
```

## Biztonsági megfontolások

1. **IP korlátozás** - Állítsd be az `PLAYER_API_ALLOWED_IPS` környezeti változót
2. **Rate limiting** - Alapértelmezett 60 kérés/perc limit
3. **API kulcs** - Opcionális API kulcs védelem
4. **Session encryption** - Session adatok titkosítása Redis-ben

## Teljesítmény optimalizálás

1. **Redis cache** - Minden permission check Redis-ből történik
2. **Eager loading** - Role-ok és permission-ök előre betöltése
3. **Batch operations** - Több permission egyszerre ellenőrzése
4. **Database indexek** - Optimalizált lekérdezések

## Troubleshooting

### Gyakori problémák

1. **Redis connection error**
   ```bash
   # Ellenőrizd a Redis szolgáltatást
   redis-cli ping
   ```

2. **Permission not found**
   ```bash
   # Futtasd újra a seedert
   php artisan db:seed --class=PlayerPermissionSeeder
   ```

3. **Cache inconsistency**
   ```bash
   # Cache újraépítése
   php artisan player-permission cache:rebuild
   ```

## Példa Hytale plugin integráció

```java
// Java plugin részlet
public void onPlayerJoin(PlayerJoinEvent event) {
    String playerId = event.getPlayer().getUniqueId().toString();
    
    // API hívás a Laravel backend-hez
    HttpResponse response = httpClient.post(
        "http://your-api.com/api/v1/player/login",
        "{"user_id": " + playerId + ", "server_name": "main"}"
    );
    
    if (response.isSuccessful()) {
        PlayerData data = gson.fromJson(response.getBody(), PlayerData.class);
        // Permissions alkalmazása...
    }
}
```

## Kapcsolat és támogatás

Ha kérdésed vagy problémád van a modullal kapcsolatban, nyiss egy issue-t a projekt repositoryjában.

## Licenc

MIT License
