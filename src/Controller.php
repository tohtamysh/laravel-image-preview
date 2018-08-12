<?php

namespace Tohtamysh\ImagePreview;


use Illuminate\Http\Request;


class Controller extends \Illuminate\Routing\Controller
{
    private $imagePreview;

    public function __construct(ImagePreview $imagePreview)
    {
        $this->imagePreview = $imagePreview;
    }

    public function preview(Request $request)
    {
        $this->imagePreview->parsePath($request->path());

        if($path = $this->imagePreview->createThumbnail())
        {
            $this->imagePreview->getFile($path);
        }else{
            throw new \Exception("Error create thumbnail");
        }
    }
}