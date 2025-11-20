# Railway Deployment - Step by Step Guide 🚂

Follow these steps to deploy your Smart Timetable System to Railway.

## 📋 Prerequisites

1. ✅ Your code is on GitHub (if not, push it first)
2. ✅ Railway account (free signup at railway.app)
3. ✅ 10-15 minutes

---

## 🚀 Step-by-Step Deployment

### Step 1: Push to GitHub (if not already done)

```bash
# If you haven't pushed to GitHub yet:
git add .
git commit -m "Prepare for Railway deployment"
git push origin main
```

### Step 2: Sign Up / Login to Railway

1. Go to [railway.app](https://railway.app)
2. Click **"Start a New Project"** or **"Login"**
3. Sign up with **GitHub** (easiest option)
4. Authorize Railway to access your GitHub

### Step 3: Create New Project

1. Click **"New Project"**
2. Select **"Deploy from GitHub repo"**
3. Find and select your **smart-timetable-system** repository
4. Railway will start detecting your project

### Step 4: Add MySQL Database

1. In your Railway project dashboard, click **"+ New"**
2. Select **"Database"**
3. Choose **"MySQL"**
4. Railway will create a MySQL database for you
5. **Note the service name** (e.g., "MySQL")

### Step 5: Configure PHP Service

1. Railway should have auto-detected your PHP service
2. Click on the **PHP service** (or "Web Service")
3. Go to **"Settings"** tab
4. Set these if needed:
   - **Root Directory:** `/` (or leave default)
   - **Build Command:** `composer install --no-dev`
   - **Start Command:** `php -S 0.0.0.0:$PORT -t .`

### Step 6: Set Environment Variables

1. Still in your **PHP service**, go to **"Variables"** tab
2. Click **"+ New Variable"**
3. Add these variables (Railway will auto-suggest MySQL vars):

   ```
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_NAME=${{MySQL.MYSQLDATABASE}}
   DB_USER=${{MySQL.MYSQLUSER}}
   DB_PASS=${{MySQL.MYSQLPASSWORD}}
   ```

   **Important:** Replace `MySQL` with your actual MySQL service name if different!

4. Add API allowed origins:
   ```
   API_ALLOWED_ORIGINS=https://your-app-name.railway.app
   ```
   (You'll get the exact URL after first deploy)

### Step 7: Get Your App URL

1. Railway will automatically assign a URL
2. Go to **"Settings"** → **"Domains"**
3. Copy your Railway URL (e.g., `https://smart-timetable-production.up.railway.app`)
4. **Update the `API_ALLOWED_ORIGINS` variable** with this URL

### Step 8: Import Database

1. Click on your **MySQL service**
2. Go to **"Data"** tab
3. Click **"Connect"** → **"MySQL Console"**
4. Copy the entire content of `database_setup.sql`
5. Paste and run it in the MySQL console
6. Wait for it to complete

**Alternative:** Use Railway's MySQL connection string:
1. Click **"Connect"** → **"Private Networking"**
2. Copy the connection details
3. Use a MySQL client (like MySQL Workbench) to connect
4. Import `database_setup.sql`

### Step 9: Verify Deployment

1. Check your **PHP service** logs (should show "Listening on port...")
2. Visit: `https://your-app.railway.app/admin/health_check.php`
   - Should return: `{"status":"ok","time":"..."}`
3. Test API endpoint:
   - `https://your-app.railway.app/admin/student_login_api.php`

### Step 10: Update Flutter App

1. Open `smart_timetable_application/lib/config/app_config.dart`
2. Update the base URL:
   ```dart
   static const String baseUrl = 'https://your-app.railway.app/admin';
   ```
3. Rebuild your Flutter app:
   ```bash
   cd smart_timetable_application
   flutter build apk --release
   ```
4. Share the APK with your tester!

---

## 🔧 Troubleshooting

### Issue: "Database connection failed"

**Solution:**
- Check environment variables are set correctly
- Verify MySQL service name matches in `${{MySQL.XXX}}` variables
- Check Railway logs for connection errors

### Issue: "404 Not Found"

**Solution:**
- Verify your `Procfile` or start command uses correct port: `$PORT`
- Check that files are in the root directory
- Verify Railway detected PHP correctly

### Issue: "CORS errors in Flutter app"

**Solution:**
- Update `API_ALLOWED_ORIGINS` with your exact Railway URL
- Check `includes/api_helpers.php` for CORS headers
- Make sure URL has `https://` not `http://`

### Issue: "Composer install fails"

**Solution:**
- Check `composer.json` is valid
- Railway might need `composer install --no-dev --optimize-autoloader`
- Check Railway logs for specific errors

---

## 📝 Environment Variables Reference

For Railway, use these format:

```
DB_HOST=${{MySQL.MYSQLHOST}}
DB_NAME=${{MySQL.MYSQLDATABASE}}
DB_USER=${{MySQL.MYSQLUSER}}
DB_PASS=${{MySQL.MYSQLPASSWORD}}
API_ALLOWED_ORIGINS=https://your-app.railway.app
```

**Note:** Replace `MySQL` with your actual service name if you named it differently!

---

## 🎯 Quick Checklist

- [ ] Code pushed to GitHub
- [ ] Railway account created
- [ ] Project created from GitHub repo
- [ ] MySQL database added
- [ ] Environment variables set
- [ ] Database imported
- [ ] Health check works
- [ ] Flutter app updated with new URL
- [ ] APK built and shared

---

## 💰 Railway Free Tier

- **$5 credit/month** (usually enough for testing)
- **500 hours** of usage
- **Auto-sleeps** after inactivity (wakes on request)
- **Unlimited** deployments

---

## 🔗 Useful Links

- [Railway Dashboard](https://railway.app/dashboard)
- [Railway Docs](https://docs.railway.app)
- [Railway Discord](https://discord.gg/railway) (for help)

---

## ✅ After Deployment

1. **Test all endpoints:**
   - Login: `POST /admin/student_login_api.php`
   - Timetable: `GET /admin/get_student_timetable.php?student_id=3`
   - Modules: `GET /admin/student_modules_api.php?student_id=3`

2. **Share with tester:**
   - Railway URL
   - Test credentials (student_id: 202057420, password: password123)
   - APK file

3. **Monitor:**
   - Check Railway dashboard for usage
   - Monitor logs for errors
   - Check database connections

---

**Need help?** Check Railway logs or their Discord community!

