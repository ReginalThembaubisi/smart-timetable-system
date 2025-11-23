# Deploying Flutter App to Railway (Same Repository)

Since your admin PHP backend and Flutter app are in the **same GitHub repository**, you'll create a **second Railway service** for the Flutter app.

## Step-by-Step Instructions

### 1. Add a New Service in Railway

1. Go to your Railway dashboard
2. Open your existing project (the one with your PHP admin backend)
3. Click **"+ New"** → **"Service"** → **"GitHub Repo"**
4. Select the **same repository** you used for the admin backend

### 2. Configure the Flutter Service

After Railway connects to your repo:

1. **Set Root Directory:**
   - Go to **Settings** → **Service Settings**
   - Find **"Root Directory"** field
   - Set it to: `smart_timetable_application`
   - This tells Railway to deploy only the Flutter app, not the PHP files

2. **Railway will auto-detect `railway.json`:**
   - Railway will find `smart_timetable_application/railway.json`
   - This configures the build and deploy commands

### 3. Set Environment Variables

1. Go to **Variables** tab in your Flutter service
2. Add this variable:
   - **Name:** `API_BASE_URL`
   - **Value:** `https://your-admin-service-url.up.railway.app/admin`
     - Replace `your-admin-service-url` with your actual PHP backend Railway URL
     - Example: `https://web-production-f8792.up.railway.app/admin`

### 4. Deploy

Railway will automatically:
- Run `build_railway.sh` (downloads Flutter SDK, builds web app)
- Start PHP server to serve static files from `build/web`
- First build takes ~5-10 minutes (Flutter SDK download)

### 5. Access Your App

- Railway will provide a URL like `https://your-flutter-app.up.railway.app`
- Open it in your browser to test

## Repository Structure

Your repo structure should look like this:
```
your-repo/
├── admin/              # PHP backend files
├── railway.json        # Admin backend Railway config
├── smart_timetable_application/
│   ├── railway.json    # Flutter app Railway config
│   ├── build_railway.sh
│   ├── lib/
│   └── ...
└── ...
```

## How It Works

- **Admin Service (PHP):**
  - Root Directory: `.` (repo root)
  - Uses `railway.json` in repo root
  - Serves PHP backend API

- **Flutter Service:**
  - Root Directory: `smart_timetable_application`
  - Uses `smart_timetable_application/railway.json`
  - Builds Flutter web app and serves static files

## Troubleshooting

**Build fails:**
- Check Railway logs for errors
- Verify Root Directory is set to `smart_timetable_application`
- Ensure `build_railway.sh` is executable (Railway handles this)

**App can't connect to API:**
- Verify `API_BASE_URL` is set correctly
- Check that your admin service URL is correct
- Ensure CORS is enabled on your PHP backend

**404 errors:**
- Verify `railway.json` points to `build/web` as web root
- Check that Flutter build completed successfully

**Port errors:**
- Railway automatically sets `$PORT` environment variable
- The start command uses `$PORT` correctly

## Quick Checklist

- [ ] Created new Railway service from same GitHub repo
- [ ] Set Root Directory to `smart_timetable_application`
- [ ] Set `API_BASE_URL` environment variable to admin backend URL
- [ ] Build completed successfully
- [ ] App loads at Railway-provided URL
- [ ] App can connect to PHP backend API
