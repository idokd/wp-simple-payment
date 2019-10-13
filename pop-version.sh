#!/bin/bash

VERSIONS=`git rev-list --tags --max-count=1`
LAST_VERSION=`git describe --tags $VERSIONS`

increment_version ()
{
  declare -a part=( ${1//\./ } )
  declare    new
  declare -i carry=1

  for (( CNTR=${#part[@]}-1; CNTR>=0; CNTR-=1 )); do
    len=${#part[CNTR]}
    new=$((part[CNTR]+carry))
    [ ${#new} -gt $len ] && carry=1 || carry=0
    [ $CNTR -gt 0 ] && part[CNTR]=${new: -len} || part[CNTR]=${new}
  done
  new="${part[*]}"
  echo -e "${new// /.}"
}

VERSION=`increment_version $LAST_VERSION`
echo "Poping version to: $VERSION"
sed -i "s/Version:[[:blank:]]*\([[:digit:]]*\.*\)\{1,3\}/Version: $VERSION/" simple-payment-plugin.php
sed -i "s/Stable tag:[[:blank:]]*\([[:digit:]]*\.*\)\{1,3\}/Stable tag: $VERSION/" readme.txt
git add .
git commit -m "$VERSION"
get tag -a $VERSION
git push origin $VERSION
