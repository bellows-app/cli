#!/bin/bash

latestTag=$(git describe --tags `git rev-list --tags --max-count=1`)

read -p "Version number (current is $latestTag):" version

mv .env .env.bak
./bellows app:build --build-version=$version
mv .env.bak .env

if [[ $(git status --porcelain) ]]; then
    git add builds/bellows
    git commit -m "Release $version"
    git push
fi

git push
git tag -a $version -m "$version"
git push --tags
echo "\n"
echo "https://github.com/bellows-app/cli/releases"
echo "https://app.anystack.sh/products/989a1946-3fef-4370-bd4f-7ef6cf3c6b27/releases"