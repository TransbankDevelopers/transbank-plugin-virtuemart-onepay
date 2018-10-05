#!/usr/bin/env bash

#Script for create the plugin artifact

echo "Travis tag: $TRAVIS_TAG"

if [[ ! -v TRAVIS_TAG ]]; then
    TRAVIS_TAG='1.0.0'
fi

PLUGIN_FILE="plugin-transbank-onepay-virtuemart3-$TRAVIS_TAG.zip"

cp CHANGELOG.md src/
cp LICENSE src/
cd src
zip -FSr ../$PLUGIN_FILE . -x *.git/\* .DS_Store* *.zip
cd ..
rm src/CHANGELOG.md
rm src/LICENSE

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
