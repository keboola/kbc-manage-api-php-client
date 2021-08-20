#!/bin/bash

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/manage-api-tests:release quay.io/keboola/manage-api-tests:$TRAVIS_TAG
docker images
docker push quay.io/keboola/manage-api-tests:$TRAVIS_TAG
