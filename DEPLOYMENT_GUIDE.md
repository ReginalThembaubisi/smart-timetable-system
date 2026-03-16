# Deployment Guide - Smart Timetable System

This guide covers deployment options for both the PHP backend and Flutter mobile app.

## 🚀 Backend Deployment (PHP + MySQL)

### Option 1: Traditional Web Hosting (Recommended for Beginners)

**Best for:** Shared hosting providers like Hostinger, SiteGround, Bluehost

#### Steps:
1. **Purchase hosting** with PHP 7.4+ and MySQL support
2. **Upload files** via FTP/cPanel File Manager
3. **Create MySQL database** in cPanel
4. **Import database schema:**
   ```sql
   -- Run database_setup.sql in phpMyAdmin
   ```
5. **Configure environment variables:**
   - Create `.env` file in root directory:
   ```env
   DB_HOST=localhost
   DB_NAME=your_database_name
   DB_USER=your_database_user
   DB_PASS=your_database_password
   API_ALLOWED_ORIGINS=https://yourdomain.com
   ```
6. **Update Flutter app** with new API URL:
   ```dart
   // lib/config/app_config.dart
   static const String baseUrl = 'https://yourdomain.com/admin';
   ```

#### Pros:
- ✅ Easy setup
- ✅ Affordable ($3-10/month)
- ✅ Full PHP/MySQL support
- ✅ Good for production

#### Cons:
- ❌ Less control
- ❌ Shared resources

---

### Option 2: Railway (Recommended for Modern Deployment)

**Best for:** Easy deployment with GitHub integration

#### Steps:
1. **Sign up** at [railway.app](https://railway.app)
2. **Create new project** from GitHub repository
3. **Add MySQL service:**
   - Click "New" → "Database" → "MySQL"
4. **Deploy PHP service:**
   - Click "New" → "GitHub Repo" → Select your repo
   - Railway auto-detects PHP
5. **Set environment variables:**
   ```env
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_NAME=${{MySQL.MYSQLDATABASE}}
   DB_USER=${{MySQL.MYSQLUSER}}
   DB_PASS=${{MySQL.MYSQLPASSWORD}}
   API_ALLOWED_ORIGINS=https://your-app.railway.app
   ```
6. **Import database:**
   - Use Railway's MySQL console or connect via MySQL client
   - Run `database_setup.sql`
7. **Update Flutter app** with Railway URL

#### Pros:
- ✅ Free tier available
- ✅ Auto-deploy from GitHub
- ✅ Easy scaling
- ✅ Built-in MySQL

#### Cons:
- ❌ Free tier has limits
- ❌ Requires credit card for some features

---

### Option 3: Render

**Best for:** Free tier with good performance

#### Steps:
1. **Sign up** at [render.com](https://render.com)
2. **Create Web Service:**
   - Connect GitHub repo
   - Environment: PHP
   - Build Command: `composer install`
   - Start Command: `php -S 0.0.0.0:8000`
3. **Create PostgreSQL/MySQL database:**
   - Render offers PostgreSQL (free) or MySQL (paid)
   - For MySQL, you may need external service like PlanetScale
4. **Set environment variables** in Render dashboard
5. **Import database schema**
6. **Update Flutter app**

#### Pros:
- ✅ Free tier (PostgreSQL)
- ✅ Auto-deploy
- ✅ Good documentation

#### Cons:
- ❌ Free tier spins down after inactivity
- ❌ MySQL requires paid plan or external service

---

### Option 4: Vercel (Limited PHP Support)

**Best for:** If you want to use Vercel (not ideal for this project)

#### Steps:
1. **Sign up** at [vercel.com](https://vercel.com)
2. **Install Vercel CLI:**
   ```bash
   npm i -g vercel
   ```
3. **Create `vercel.json`:**
   ```json
   {
     "version": 2,
     "builds": [
       {
         "src": "**/*.php",
         "use": "@vercel/php"
       }
     ],
     "routes": [
       {
         "src": "/(.*)",
         "dest": "/$1"
       }
     ]
   }
   ```
4. **Use external MySQL:**
   - PlanetScale (free tier)
   - Railway MySQL
   - Or your own MySQL server
5. **Deploy:**
   ```bash
   vercel
   ```

#### Pros:
- ✅ Great for static/frontend
- ✅ Free tier
- ✅ Fast CDN

#### Cons:
- ❌ PHP support is limited
- ❌ No built-in MySQL
- ❌ Not ideal for this project

---

### Option 5: DigitalOcean App Platform

**Best for:** Professional deployment with managed services

#### Steps:
1. **Sign up** at [digitalocean.com](https://digitalocean.com)
2. **Create App** from GitHub
3. **Add MySQL database** (managed service)
4. **Configure environment variables**
5. **Deploy**

#### Pros:
- ✅ Professional hosting
- ✅ Managed MySQL
- ✅ Good performance
- ✅ Auto-scaling

#### Cons:
- ❌ Paid service ($5+/month)
- ❌ More complex setup

---

## 📱 Flutter App Deployment

### Mobile App (Android/iOS)

#### Android (Google Play Store):
1. **Build release APK:**
   ```bash
   cd smart_timetable_application
   flutter build apk --release --flavor student -t lib/main.dart
   flutter build apk --release --flavor lecturer -t lib/main_lecturer.dart
   ```
2. **Or build App Bundle (recommended):**
   ```bash
   flutter build appbundle --flavor student -t lib/main.dart
   flutter build appbundle --flavor lecturer -t lib/main_lecturer.dart
   ```
3. **Upload to Google Play Console**
4. **Update API URL** in production build

#### Android release signing (required for production)
1. Create/upload a release keystore file (for example: `android/keystore/release.jks`).
2. Copy `android/key.properties.example` to `android/key.properties`.
3. Fill real values in `android/key.properties`:
   ```properties
   storeFile=../keystore/release.jks
   storePassword=your_store_password
   keyAlias=your_key_alias
   keyPassword=your_key_password
   ```
4. Build release again using flavor commands above.
5. Verify output files:
   - `build/app/outputs/flutter-apk/app-student-release.apk`
   - `build/app/outputs/flutter-apk/app-lecturer-release.apk`

#### iOS (App Store):
1. **Build iOS app:**
   ```bash
   flutter build ios --release
   ```
2. **Open in Xcode:**
   ```bash
   open ios/Runner.xcworkspace
   ```
3. **Archive and upload** via Xcode
4. **Submit to App Store Connect**

### Flutter Web (Optional)

If you want to deploy Flutter web version:

#### Vercel/Netlify:
```bash
cd smart_timetable_application
flutter build web
# Deploy the build/web folder
```

---

## 🔧 Pre-Deployment Checklist

### Backend:
- [ ] Update `.env` with production credentials
- [ ] Remove debug code
- [ ] Enable error logging (disable error display)
- [ ] Set proper CORS headers
- [ ] Test all API endpoints
- [ ] Backup database
- [ ] Update API base URL in Flutter app
- [ ] Test with production database

### Flutter App:
- [ ] Update `app_config.dart` with production API URL
- [ ] Test all features with production backend
- [ ] Build release version
- [ ] Test on physical devices
- [ ] Update app version in `pubspec.yaml`

---

## 🔒 Security Checklist

- [ ] Use HTTPS (SSL certificate)
- [ ] Set strong database passwords
- [ ] Use environment variables (never commit secrets)
- [ ] Enable CORS only for your app domain
- [ ] Implement rate limiting
- [ ] Use prepared statements (already done ✅)
- [ ] Hash passwords (already done ✅)
- [ ] Regular backups

---

## 📝 Environment Variables Template

Create `.env` file (never commit to Git):

```env
# Database
DB_HOST=localhost
DB_NAME=smart_timetable
DB_USER=your_user
DB_PASS=your_secure_password

# API
API_ALLOWED_ORIGINS=https://yourdomain.com,https://app.yourdomain.com

# Optional: Add more as needed
```

---

## 🆘 Troubleshooting

### Common Issues:

1. **Database connection fails:**
   - Check DB credentials
   - Verify database exists
   - Check firewall rules

2. **CORS errors:**
   - Update `API_ALLOWED_ORIGINS` in `.env`
   - Check `api_helpers.php` CORS settings

3. **404 errors:**
   - Check `.htaccess` file (if using Apache)
   - Verify file paths
   - Check server rewrite rules

4. **Flutter app can't connect:**
   - Verify API URL is correct
   - Check HTTPS/HTTP protocol
   - Test API endpoints in browser/Postman

---

## 💡 Recommended Setup

**For Production:**
- **Backend:** Railway or DigitalOcean (best balance of ease and features)
- **Database:** Managed MySQL service
- **Flutter App:** Google Play Store / App Store

**For Testing/Demo:**
- **Backend:** Render free tier or Railway
- **Database:** Included with hosting
- **Flutter App:** TestFlight (iOS) or Internal Testing (Android)

---

## 📚 Additional Resources

- [Railway Documentation](https://docs.railway.app)
- [Render Documentation](https://render.com/docs)
- [Vercel PHP Documentation](https://vercel.com/docs/frameworks/php)
- [Flutter Deployment Guide](https://docs.flutter.dev/deployment)

---

**Need help?** Check your hosting provider's documentation or open an issue on GitHub.

