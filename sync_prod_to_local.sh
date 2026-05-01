#!/bin/bash

FILE=$1

grep -v "GTID_PURGED" "$FILE" \
| sed 's/DEFINER=`[^`]*`@`[^`]*`//g' \
| mysql -u Geoff -p focus_local
