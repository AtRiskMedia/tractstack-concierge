#!/bin/bash

# Generate Unix timestamp
TIMESTAMP=$(date +%s)

# Write to concierge.json
echo "{\"concierge\": $TIMESTAMP}" > concierge.json

# Stage the file if it’s modified
git add concierge.json

# Exit successfully to allow the push
exit 0
