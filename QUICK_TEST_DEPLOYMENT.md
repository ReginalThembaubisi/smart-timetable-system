# Quick Testing Deployment - Get Online Fast! 🚀

This is for **testing only** - getting your app online quickly so someone can test it.

## ⚡ Fastest Option: Railway (Recommended - 5 minutes)

### Steps:
1. **Go to** [railway.app](https://railway.app) and sign up with GitHub
2. **Click "New Project"** → "Deploy from GitHub repo"
3. **Select your repository**
4. **Add MySQL Database:**
   - Click "New" → "Database" → "MySQL"
5. **Set Environment Variables:**
   - Click on your PHP service → "Variables" tab
   - Add these:
     ```
     DB_HOST=${{MySQL.MYSQLHOST}}
     DB_NAME=${{MySQL.MYSQLDATABASE}}
     DB_USER=${{MySQL.MYSQLUSER}}
     DB_PASS=${{MySQL.MYSQLPASSWORD}}
     ```
6. **Import Database:**
   - Click on MySQL service → "Connect" → "MySQL Console"
   - Copy/paste your `database_setup.sql` content
7. **Get your URL:**
   - Railway gives you a URL like: `https://your-app.railway.app`
   - Your API will be at: `https://your-app.railway.app/admin`
8. **Update Flutter app:**
   - Change API URL to: `https://your-app.railway.app/admin`
   - Rebuild and send APK to tester

**Done!** Share the Railway URL with your tester.

---

## 🔥 Alternative: ngrok (Super Quick - 2 minutes)

If you just want to share your local XAMPP server temporarily:

### Steps:
1. **Download ngrok:** [ngrok.com/download](https://ngrok.com/download)
2. **Start XAMPP** (Apache + MySQL)
3. **Run ngrok:**
   ```bash
   ngrok http 80
   ```
   (Or `ngrok http 8000` if you're using port 8000)
4. **Copy the HTTPS URL** ngrok gives you (e.g., `https://abc123.ngrok.io`)
5. **Update Flutter app** with: `https://abc123.ngrok.io/admin`
6. **Share the APK** with your tester

**Note:** 
- ✅ Free and instant
- ❌ URL changes each time you restart
- ❌ Only works when your computer is on
- ❌ Not secure for production

---

## 🌐 Option 2: Render (Free, but slower)

### Steps:
1. **Sign up** at [render.com](https://render.com) (GitHub login)
2. **New Web Service** → Connect your GitHub repo
3. **Settings:**
   - Environment: **PHP**
   - Build Command: `composer install`
   - Start Command: `php -S 0.0.0.0:8000`
4. **Add PostgreSQL Database** (free):
   - New → PostgreSQL
   - Or use external MySQL (PlanetScale free tier)
5. **Set environment variables**
6. **Deploy**

**Note:** Free tier spins down after 15 min inactivity (wakes up on first request)

---

## 📱 For Flutter App Testing

### Quick APK Sharing:
1. **Build APK:**
   ```bash
   cd smart_timetable_application
   flutter build apk --release
   ```
2. **Find APK:** `build/app/outputs/flutter-apk/app-release.apk`
3. **Share via:**
   - Google Drive
   - Dropbox
   - Email (if small enough)
   - Firebase App Distribution (free)

### Update API URL in Flutter:
Edit `lib/config/app_config.dart`:
```dart
static const String baseUrl = 'https://your-railway-url.railway.app/admin';
// or
static const String baseUrl = 'https://your-ngrok-url.ngrok.io/admin';
```

---

## 🎯 Recommended for Quick Testing

**Best choice:** **Railway** 
- ✅ Free tier
- ✅ Permanent URL
- ✅ Works 24/7
- ✅ Easy setup
- ✅ Built-in MySQL

**Quickest:** **ngrok**
- ✅ Instant
- ✅ No deployment needed
- ❌ Temporary
- ❌ Requires your PC on

---

## ⚙️ Quick Setup Script for Railway

After deploying to Railway, run this in Railway's MySQL console:

```sql
-- Paste your database_setup.sql content here
-- Or just the essential tables
```

Then test your API:
- `https://your-app.railway.app/admin/student_login_api.php`
- `https://your-app.railway.app/admin/get_student_timetable.php?student_id=3`

---

## 📝 What to Share with Tester

1. **APK file** (Flutter app)
2. **Test credentials:**
   - Student ID: `202057420`
   - Password: `password123`
3. **Any special instructions**

---

## ⚠️ Important Notes

- These are for **testing only**, not production
- Free tiers have limits (traffic, storage, etc.)
- Railway free tier: $5 credit/month
- Render free tier: spins down after inactivity
- ngrok free: URL changes each restart

---

**Need help?** Railway has great docs and support. Render is also straightforward.

**Time estimate:**
- Railway: 5-10 minutes
- ngrok: 2 minutes (but temporary)
- Render: 10-15 minutes

