<?php

Route::get('/preview/{param}', '\Tohtamysh\ImagePreview\Controller@preview')->where('param', '.+');