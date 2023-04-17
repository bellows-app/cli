#!/bin/bash

latestTag=$(git describe --tags `git rev-list --tags --max-count=1`)

read -p "Version number (current is $latestTag):" version

mv .env .env.bak
./bellows app:build --build-version=$version
mv .env.bak .env

git push
git tag -a $version -m "$version"
git push --tags
echo "\n"
echo "https://github.com/bellows-app/cli/releases"
# https://github.com/bellows-app/cli/tags
# https://github.com/bellows-app/cli/actions
# https://github.com/bellows-app/cli/releases