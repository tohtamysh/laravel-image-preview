<?php

namespace Tohtamysh\ImagePreview;


class Facade extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return ImagePreview::class;
    }

}