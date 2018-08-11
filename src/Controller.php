<?php

namespace Tohtamysh\ImagePreview;


use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tohtamysh\ImagePreview\Exception\FileNotFound;
use Tohtamysh\ImagePreview\Exception\ParamException;

class Controller extends \Illuminate\Routing\Controller
{
    public function preview(Request $request)
    {
        $param = ImagePreview::parsePath($request->path());

        $cachePath = 'cache' . DIRECTORY_SEPARATOR . $param['uri'] . DIRECTORY_SEPARATOR . $param['file'];

        if (Storage::disk('local')->exists($cachePath)) {
            $this->getFile($cachePath);
        }

        if (Storage::disk('local')->exists($param->file)) {
            $image = Image::make(Storage::disk('local')->get($param->file));
        } else {
            throw new FileNotFound('File ' . $param->file . ' not found');
        }

        if(!$param->width && !$param->height){
            throw new ParamException('Uri param error');
        }

        if ($param->width && !$param->height) {
            $image->widen($param->width, function ($constraint) {
                $constraint->upsize();
            });
        } elseif ($param->height && !$param->width) {
            $image->heighten($param->height, function ($constraint) {
                $constraint->upsize();
            });
        } else {
            if ($param->extention) {
                $new_img = Image::canvas($param->width, $param->height, '#' . $param->color);

                $image->resize($param->width, $param->height, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });

                if ($image->width() >= $image->height()) {
                    $new_img->insert($image, 'left');
                } else {
                    $new_img->insert($image, 'top-center');
                }

                $image->destroy();

                $image = $new_img;
            } else {
                $image->fit($param->width, $param->height, function ($constraint) {
                    $constraint->upsize();
                }, 'top-left');
            }
        }

        $this->putFile($cachePath, $image->encode());

        $this->getFile($cachePath);
    }

    private function getFile(string $path)
    {
        $image = Image::make(Storage::disk('local')->get($path));

        header('Content-Type:' . $image->mime);
        header('Content-Length: ' . Storage::disk('local')->size($path));
        readfile(Storage::disk('local')->get($path));
        exit();
    }

    private function putFile(string $path, string $image)
    {
        Storage::disk('local')->put($path, $image);

        ImagePreview::optimizeImage(storage_path('app/' . $path));
    }
}