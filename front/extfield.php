<?php

$plugin = new Plugin();
if (!$plugin->isActivated('jamf')) {
    Html::displayNotFoundError();
}
