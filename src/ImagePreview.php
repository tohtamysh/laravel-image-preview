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
    private $param;

    public function __construct($params = null)
    {
        $param = new \stdClass();

        $param->width = $params['width'] ?? null; // щирина
        $param->height = $params['height'] ?? null; // высота
        $param->color = $params['color'] ?? null; // цвет
        $param->extention = $params['extention'] ?? false; //дорисовать
        $param->file = $params['file'] ?? null; // файл
        $param->uri = ''; // параметры запроса (w100h200cr000000)

        if ($param->width) {
            $param->uri .= 'w' . $param->width;
        }

        if ($param->height) {
            $param->uri .= 'h' . $param->height;
        }

        if ($param->color) {
            $param->uri .= 'cr' . $param->color;
        }

        if ($param->extention) {
            $param->uri .= 'ext';
        }

        $this->param = $param;
    }

    public function parsePath($uri)
    {
        if (preg_match('|^preview/(.*)(w(\d+))(.*)/|', $uri, $match)) {
            $this->param->width = $match[3];
        }

        if (preg_match('|^preview/(.*)(h(\d+))(.*)/|', $uri, $match)) {
            $this->param->height = $match[3];
        }

        if (preg_match('|^preview/(.*)cr([abcdefABCDEF0-9]{6})(.*)/|', $uri, $match)) {
            $this->param->color = $match[2];
        }

        if (preg_match('|^preview/(.*)ext(.*)/|', $uri, $match)) {
            $this->param->extention = true;
        }

        if (preg_match('~^preview/([^\/]+)/(.+.(?:gif|jpe?g|png))$~', $uri, $match)) {
            $this->param->file = $match[2];
        }

        if (preg_match('~^preview/([^\/]+)/(.+.(?:gif|jpe?g|png))$~', $uri, $match)) {
            $this->param->uri = $match[1];
        }
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
        header('Cache-Control: privat; max-age=31536000');
        header("Expires: " . (gmdate("D, d M Y H:i:s", time() + (60 * 60 * 24 * 365)) . " GMT"));
        header("Pragma: cache");
        header("Cache-Control: max-age=" . (60 * 60 * 24 * 365));
        readfile(Storage::disk('public')->getDriver()->getAdapter()->applyPathPrefix($path));
        exit();
    }

    public function createThumbnail()
    {
        if (!$this->param->uri || !$this->param->file) {
            throw new \Exception("Uri and/or file param error");
        }
        $cachePath = 'cache' . DIRECTORY_SEPARATOR . $this->param->uri . DIRECTORY_SEPARATOR . $this->param->file;

        if (Storage::disk('public')->exists($cachePath)) {
            $this->getFile($cachePath);
        }

        if (Storage::disk('public')->exists($this->param->file)) {
            $image = Image::make(Storage::disk('public')->get($this->param->file));
        } else {
            throw new FileNotFoundException('File ' . $this->param->file . ' not found');
        }

        if (!$this->param->width && !$this->param->height) {
            throw new ParamException('Uri param error');
        }

        if ($this->param->width && !$this->param->height) {
            $image->widen($this->param->width, function ($constraint) {
                $constraint->upsize();
            });
        } elseif ($this->param->height && !$this->param->width) {
            $image->heighten($this->param->height, function ($constraint) {
                $constraint->upsize();
            });
        } else {
            if ($this->param->extention) {
                $new_img = Image::canvas($this->param->width, $this->param->height, '#' . $this->param->color);

                $image->resize($this->param->width, $this->param->height, function ($constraint) {
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
                $image->fit($this->param->width, $this->param->height, function ($constraint) {
                    $constraint->upsize();
                }, 'top-left');
            }
        }

        $this->putFile($cachePath, $image->encode());

        return $cachePath;
    }
}