## Image preview for Laravel

The package creates thumbnails and stores them in storage.

[![Latest Stable Version](https://poser.pugx.org/tohtamysh/laravel-image-preview/v/stable)](https://packagist.org/packages/tohtamysh/laravel-image-preview) [![License](https://poser.pugx.org/tohtamysh/laravel-image-preview/license)](https://packagist.org/packages/tohtamysh/laravel-image-preview)

### URL example:

```
/preview/w100h200cr000000ext/news/file.jpg
```
create thumbnail with

width - 100px

height - 200px

background color - #000000

ext - extention

#### Programmatically create thumbnail

```php
$imagePreview = new ImagePreview(['width' => 200, 'file' => $path]);

$cachePath = $imagePreview->createThumbnail();
```