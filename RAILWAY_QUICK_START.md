# Railway Quick Start - 5 Minute Guide ⚡

## 🚀 Super Quick Steps

1. **Push to GitHub** (if not done)
   ```bash
   git add .
   git commit -m "Ready for Railway"
   git push
   ```

2. **Go to Railway**
   - Visit [railway.app](https://railway.app)
   - Sign up with GitHub
   - Click "New Project" → "Deploy from GitHub repo"

3. **Add MySQL**
   - Click "+ New" → "Database" → "MySQL"

4. **Set Environment Variables**
   - Click on PHP service → "Variables" tab
   - Add these (Railway auto-suggests them):
     ```
     DB_HOST=${{MySQL.MYSQLHOST}}
     DB_NAME=${{MySQL.MYSQLDATABASE}}
     DB_USER=${{MySQL.MYSQLUSER}}
     DB_PASS=${{MySQL.MYSQLPASSWORD}}
     ```

5. **Get Your URL**
   - Railway gives you a URL automatically
   - Copy it (e.g., `https://smart-timetable.up.railway.app`)
   - Add to variables: `API_ALLOWED_ORIGINS=https://your-url.railway.app`

6. **Import Database**
   - Click MySQL service → "Data" → "Connect" → "MySQL Console"
   - Copy/paste entire `database_setup.sql` file
   - Run it

7. **Test**
   - Visit: `https://your-url.railway.app/admin/health_check.php`
   - Should see: `{"status":"ok"}`

8. **Update Flutter App**
   - Change API URL to: `https://your-url.railway.app/admin`
   - Build APK and share!

## ✅ Done!

Your API is now live at: `https://your-url.railway.app/admin`

---

## 🔑 Test Credentials

- Student ID: `202057420`
- Password: `password123`

---

## 📝 Important Notes

- Replace `MySQL` in env vars with your actual service name if different
- Railway free tier: $5/month credit (usually enough for testing)
- App auto-sleeps after inactivity (wakes on first request)

---

**Full guide:** See `RAILWAY_DEPLOYMENT_STEPS.md` for detailed instructions.

