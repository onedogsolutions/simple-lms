#!/bin/bash

# Simple LMS Bridge Deployment Script
# Steps: Build assets, Zip plugin, Git Commit, Git Push

echo "Step 1: Running npm run build..."
npm run build

echo "Step 2: Creating plugin zip archive..."
# Exclude .git, node_modules, src, and .DS_Store
zip -r ../simple-lms-bridge.zip . -x "*.git*" "node_modules/*" "src/*" "*.DS_Store*"

echo "Step 3: Staging changes for Git..."
git add .

echo "Step 4: Committing changes..."
git commit -m "Fix: Prevent active enrollment for expired PMPro migrations and add retroactive certificate de-access."

echo "Step 5: Pushing to main..."
git push origin main

echo "Deployment complete!"