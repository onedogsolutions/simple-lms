#!/bin/bash

# Deployment Pipeline for One Dog Solutions LMS

echo "🚀 Starting build and deployment process..."

# 1. Compile React components and Tailwind CSS v4
echo "📦 Building assets..."
npm run build
if [ $? -ne 0 ]; then
    echo "❌ Build failed. Aborting deployment."
    exit 1
fi

# 2. Create the zip archive in the parent directory
echo "🗜️  Zipping plugin to parent directory..."
zip -r simple-lms-bridge.zip . -x "*.git*" "*node_modules*" "*src*" "*.DS_Store*" "deploy.sh"

# 3. Stage changes to Git
echo "➕ Staging files to Git..."
git add .

# 4. Commit changes
echo "💾 Committing changes..."
git commit -m "Fix: Implement fuzzy certificate matching, retroactive graduation cleanup, and automated deployment."

# 5. Push to remote
echo "☁️  Pushing to GitHub..."
git push origin main

echo "✅ Deployment pipeline complete!"