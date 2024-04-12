#!/bin/bash
set -e -u -x -o pipefail

docker-compose pull --quiet
docker-compose up --no-start
docker-compose start

# Check services health (because the DB container is not ready by the time we try to install GLPI)
for CONTAINER_ID in `docker-compose ps -a -q`; do
  CONTAINER_NAME=`/usr/bin/docker inspect --format='{{print .Name}}{{if .Config.Image}} ({{print .Config.Image}}){{end}}' $CONTAINER_ID`
  HEALTHY=false
  TOTAL_COUNT=0
  until [ $HEALTHY = true ]; do
    if [ "`/usr/bin/docker inspect --format='{{if .Config.Healthcheck}}{{print .State.Health.Status}}{{else}}{{print \"healthy\"}}{{end}}' $CONTAINER_ID`" == "healthy" ]
    then
      HEALTHY=true
      echo "$CONTAINER_NAME is healthy"
    else
      if [ $TOTAL_COUNT -eq 15 ]
      then
        echo "$CONTAINER_NAME fails to start"
        exit 1
      fi
      echo "Waiting for $CONTAINER_NAME to be ready..."
      sleep 2
      TOTAL_COUNT=$[$TOTAL_COUNT +1]
    fi
  done
done

sleep 5
