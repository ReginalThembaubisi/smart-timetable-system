# Manual Railway Deployment

If Railway is not auto-deploying, you can manually trigger a deployment:

## Option 1: Redeploy from Railway Dashboard

1. Go to your Railway project: https://railway.app/dashboard
2. Click on your "web" service
3. Go to "Deployments" tab
4. Click the three dots (⋯) on the latest deployment
5. Select "Redeploy"

## Option 2: Trigger via Railway CLI

If you have Railway CLI installed:
```bash
railway up
```

## Option 3: Check Auto-Deploy Settings

1. Go to your Railway project
2. Click on "Settings"
3. Check "Source" section
4. Make sure "Auto Deploy" is enabled
5. If disabled, enable it and save

## Option 4: Force a New Commit

Sometimes making a small change and pushing again triggers deployment.

