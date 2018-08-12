<?php

namespace Tohtamysh\ImagePreview;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Tohtamysh\ImagePreview\Exception\ParamException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;


class ImagePreview
{
    public function parsePath($uri)
    {
        $param = new \stdClass();

        $param->width = null; // щирина
        $param->height = null; // высота
        $param->color = null; // цвет
        $param->extention = false; //дорисовать
        $param->file = null; // файл
        $param->uri = null; // параметры запроса (w100h200cr000000)

        if (preg_match('|^preview/(.*)(w(\d+))(.*)/|', $uri, $match)) {
            $param->width = $match[3];
        }

        if (preg_match('|^preview/(.*)(h(\d+))(.*)/|', $uri, $match)) {
            $param->height = $match[3];
        }

        if (preg_match('|^preview/(.*)cr([abcdefABCDEF0-9]{6})(.*)/|', $uri, $match)) {
            $param->color = $match[2];
        }

        if (preg_match('|^preview/(.*)ext(.*)/|', $uri, $match)) {
            $param->extention = true;
        }

        if (preg_match('~^preview/([^\/]+)/(.+.(?:gif|jpe?g|png))$~', $uri, $match)) {
            $param->file = $match[2];
        }

        if (preg_match('~^preview/([^\/]+)/(.+.(?:gif|jpe?g|png))$~', $uri, $match)) {
            $param->uri = $match[1];
        }

        return $param;
    }

    public function optimize(string $path)
    {
        $mymeType = File::mimeType($path);

        if ($mymeType === 'image/jpeg') {
            $command = '/usr/bin/convert ' . $path . ' -sampling-factor 4:2:0 -strip -quality 85 -interlace JPEG -colorspace RGB ' . $path;
        }

        if ($mymeType === 'image/png') {
            $command = '/usr/bin/convert ' . $path . ' -strip ' . $path;
        }

        if (function_exists('proc_open') && isset($command)) {
            $process = new Process($command);
            $process->run();
        }
    }

    private function putFile(string $path, string $image)
    {
        Storage::disk('public')->put($path, $image);

        $this->optimize(Storage::disk('public')->getDriver()->getAdapter()->applyPathPrefix($path));
    }

    public function getFile(string $path)
    {
        $image = Image::make(Storage::disk('public')->get($path));

        header('Content-Type:' . $image->mime);
        header('Content-Length: ' . Storage::disk('public')->size($path));
        readfile(Storage::disk('public')->getDriver()->getAdapter()->applyPathPrefix($path));
        exit();
    }

    public function createThumbnail(\stdClass $param)
    {
        $cachePath = 'cache' . DIRECTORY_SEPARATOR . $param->uri . DIRECTORY_SEPARATOR . $param->file;

        if (Storage::disk('public')->exists($cachePath)) {
            $this->getFile($cachePath);
        }

        if (Storage::disk('public')->exists($param->file)) {
            $image = Image::make(Storage::disk('public')->get($param->file));
        } else {
            throw new FileNotFoundException('File ' . $param->file . ' not found');
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

        return $cachePath;
    }
}