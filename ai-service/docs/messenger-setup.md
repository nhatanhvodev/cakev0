# Messenger Product Template Setup Guide

This guide walks through setting up Facebook Messenger integration with product card templates for the CakeV0 bot.

## Prerequisites

- Facebook Business Account
- ngrok installed (for local tunnel testing)
- Environment variables ready to configure

## Step 1: Create Facebook Business App

1. Go to [Meta for Developers](https://developers.facebook.com/)
2. Click **My Apps** → **Create App**
3. Choose **Business** as the app type
4. Fill in app details:
   - **App Name**: e.g., "CakeV0 Messenger Bot"
   - **App Contact Email**: your business email
   - **Select App Purpose**: Business
5. Click **Create App**

## Step 2: Add Messenger Product

1. In your app dashboard, click **+ Add Product**
2. Search for **Messenger** and click **Set Up**
3. This enables Messenger API for your app

## Step 3: Create Facebook Page & Get Page Token

1. Go to [Meta Business Suite](https://business.facebook.com/)
2. Create or select a **Facebook Page** (or Pages linked to your Business Account)
3. In your app dashboard → **Messenger** → **Settings** → **Access Tokens**
4. Select your Page and generate a **Page Access Token**
5. Copy this token — you'll need it for `FB_PAGE_TOKEN`

## Step 4: Get App Secret & Verify Token

1. In your app dashboard, go to **Settings** → **Basic**
2. Copy your **App ID** and **App Secret**
3. Create a **Verify Token** (any secure random string, e.g., `openssl rand -hex 16`)
   - This is for webhook verification only
4. Set three environment variables:
   ```bash
   export FB_PAGE_TOKEN="your_page_access_token"
   export FB_VERIFY_TOKEN="your_verify_token"
   export FB_APP_SECRET="your_app_secret"
   ```

## Step 5: Run Local Tunnel with ngrok

1. Install [ngrok](https://ngrok.com/)
2. Start your app (e.g., `cd ai-service && python -m uvicorn app.main:app --port 8000`)
3. In another terminal, expose the local server:
   ```bash
   ngrok http 8000
   ```
4. Copy the HTTPS URL from ngrok (e.g., `https://xxxx-xx-xxx-xxx-xx.ngrok-free.app`)

## Step 6: Configure Webhook Callback URL

1. In your app dashboard → **Messenger** → **Settings** → **Webhooks**
2. Click **Add Callback URL**
3. Enter:
   - **Callback URL**: `https://<your-ngrok-url>/channels/messenger/webhook`
   - **Verify Token**: the token you created in Step 4
4. Click **Verify and Save**
5. Subscribe to the `messages` webhook field:
   - Find the **Webhooks** section
   - Under "Select which events your webhooks should subscribe to", ensure **messages** is checked
   - Click **Save**

## Step 7: Add Tester Accounts (Important for Development)

**⚠️ App Review Risk:**  
Messenger API requires app review for production access to all users. Until approved, your bot can only send messages to:
- App admins
- App developers
- Test users/accounts

**For Development:**
1. In your app dashboard → **Roles** → **Test Users**
2. Create test user accounts
3. Add these test accounts to your Facebook Page
4. In the Page settings, go to **Test Users** and assign roles

Once test users have Page roles, they can message the Page and receive replies, including product cards.

## Step 8: Send Product Cards

The bot automatically sends product cards when:
1. A customer sends a message to the Page
2. The AI engine replies with `reply.products` containing product data
3. The webhook sends a generic template with:
   - Product title
   - Formatted VND price
   - Product image (absolute or relative URL)
   - Click-through link to product detail page

## Testing

1. Message your test Page with a query (e.g., "show me cakes")
2. The bot replies with text + a product card carousel
3. Check ngrok logs for incoming webhook requests

## Production Deployment

For production:
1. **Submit for App Review** in Meta for Developers
2. Request `pages_messaging` and `pages_read_engagement` permissions
3. Once approved, the bot can message all Page followers
4. Deploy the app to a production server (not ngrok) and update Callback URL

## Troubleshooting

- **"Callback URL Refused"**: Ensure ngrok is running and the webhook path is correct
- **"Verify Token Failed"**: Double-check the `FB_VERIFY_TOKEN` in settings.py matches what you entered in Meta
- **No Messages Received**: Ensure the Page has permissions and your test user has proper roles
- **Product Cards Not Showing**: Check that `reply.products` is populated by the AI engine
