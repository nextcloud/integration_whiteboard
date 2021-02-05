#!/bin/bash

# this script helps to prepare a release
# it gets and prepare spacedeck

if [ -z "$1" ]; then
    echo "give spacedeck path as the first argument"
    exit 1
fi

rm -rf spacedeck
# copy spacedeck
cp -r "$1" ./spacedeck
# fix style.css
sed -i 's|"/images/|"../images/|g' spacedeck/public/stylesheets/style.css
sed -i 's|"/fonts/|"../fonts/|g' spacedeck/public/stylesheets/style.css
# cleanup
rm -rf spacedeck/storage spacedeck/database.sqlite
# put our initial database
cp database.sqlite spacedeck/
# cleanup
cd spacedeck
rm -rf .dockerignore .git/ .github/ .gitignore Dockerfile spacedeck.nexe.bin spacedeck.pkg.bin
#npm run pkg
npm run nexe
