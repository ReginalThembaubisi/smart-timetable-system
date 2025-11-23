# Railway Next Steps - After Successful Deployment ✅

Your app is deployed! Now let's complete the setup.

## 🎯 Current Status
- ✅ PHP service deployed: `web-production-f8792.up.railway.app`
- ✅ Deployment successful
- ⏳ Need to add MySQL database
- ⏳ Need to configure environment variables
- ⏳ Need to import database

---

## Step 1: Add MySQL Database

1. In your Railway project (`happy-presence`), click **"+ New"** button
2. Select **"Database"**
3. Choose **"MySQL"**
4. Railway will create a MySQL service for you
5. **Note the service name** (probably "MySQL" or "mysql")

---

## Step 2: Set Environment Variables

1. Click on your **"web"** service
2. Go to **"Variables"** tab
3. Click **"+ New Variable"**
4. Add these variables (Railway will auto-suggest MySQL vars):

   ```
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_NAME=${{MySQL.MYSQLDATABASE}}
   DB_USER=${{MySQL.MYSQLUSER}}
   DB_PASS=${{MySQL.MYSQLPASSWORD}}
   ```

   **Important:** Replace `MySQL` with your actual MySQL service name if it's different!

5. Add API allowed origins:
   ```
   API_ALLOWED_ORIGINS=https://web-production-f8792.up.railway.app
   ```

6. Click **"Add"** for each variable

---

## Step 3: Import Database

### Option A: Using Railway MySQL Console (Easiest)

1. Click on your **MySQL service**
2. Go to **"Data"** tab
3. Click **"Connect"** → **"MySQL Console"**
4. Open your `database_setup.sql` file locally
5. Copy the **entire content**
6. Paste into the MySQL console
7. Press Enter/Execute
8. Wait for it to complete

### Option B: Using MySQL Client

1. Click on MySQL service → **"Connect"** → **"Private Networking"**
2. Copy the connection details:
   - Host
   - Port
   - Database
   - Username
   - Password
3. Use MySQL Workbench or command line:
   ```bash
   mysql -h [HOST] -P [PORT] -u [USER] -p [DATABASE] < database_setup.sql
   ```

---

## Step 4: Test Your API

1. **Health Check:**
   ```
   https://web-production-f8792.up.railway.app/admin/health_check.php
   ```
   Should return: `{"status":"ok","time":"..."}`

2. **Test Login API:**
   ```
   POST https://web-production-f8792.up.railway.app/admin/student_login_api.php
   Body: {"student_number":"202057420","password":"password123"}
   ```

3. **Test Timetable:**
   ```
   https://web-production-f8792.up.railway.app/admin/get_student_timetable.php?student_id=3
   ```

---

## Step 5: Update Flutter App

1. Open `smart_timetable_application/lib/config/app_config.dart`
2. Update the base URL:
   ```dart
   static const String baseUrl = 'https://web-production-f8792.up.railway.app/admin';
   ```
3. Rebuild your Flutter app:
   ```bash
   cd smart_timetable_application
   flutter build apk --release
   ```
4. Share the APK with your tester!

---

## 🔍 Troubleshooting

### Database Connection Fails
- Check environment variables are set correctly
- Verify MySQL service name matches in `${{MySQL.XXX}}` variables
- Check Railway logs for connection errors

### 404 Errors
- Make sure files are in the root directory
- Check that `Procfile` is correct
- Verify Railway detected PHP correctly

### CORS Errors
- Update `API_ALLOWED_ORIGINS` with exact Railway URL
- Make sure URL includes `https://`

---

## ✅ Checklist

- [ ] MySQL database added
- [ ] Environment variables set
- [ ] Database imported
- [ ] Health check works
- [ ] API endpoints tested
- [ ] Flutter app updated with new URL
- [ ] APK built and ready to share

---

## 🎉 You're Almost Done!

Once you complete these steps, your Smart Timetable System will be fully deployed and ready for testing!

**Your API URL:** `https://web-production-f8792.up.railway.app/admin`

