#!/usr/bin/env bash

#Script for create the plugin artifact

if [[ ! -v TRAVIS_TAG ]]; then
    TRAVIS_TAG='1.0.0'
fi

PLUGIN_FILE="plugin-transbank-onepay-virtuemart-$TRAVIS_TAG.zip"

zip -FSr $PLUGIN_FILE . -x docs/\* *.git/\* .DS_Store* .editorconfig* .gitignore* .vscode/\* *.sh .travis* README.md *.zip docker-virtuemart3/\*

echo "Plugin version: $TRAVIS_TAG"
echo "Plugin file: $PLUGIN_FILE"
