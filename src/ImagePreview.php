<?php

namespace Tohtamysh\ImagePreview;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;


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
}