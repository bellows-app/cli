#!/bin/bash

latestTag=$(git describe --tags `git rev-list --tags --max-count=1`)

read -p "Version number (current is $latestTag):" version

mv .env .env.bak
./bellows app:build --build-version=$version
mv .env.bak .env