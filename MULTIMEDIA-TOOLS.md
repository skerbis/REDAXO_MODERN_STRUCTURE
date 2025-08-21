# Multimedia Tools Available in Docker Images

Both development and production Docker images now include comprehensive multimedia processing tools to support image, video, audio, and document processing within REDAXO applications.

## Image Processing

### ImageMagick (CLI tools)
- **convert**: Image format conversion, resizing, and manipulation
- **identify**: Image format detection and metadata extraction
- **composite**: Image composition and layering
- **montage**: Create image montages and thumbnails

**Version**: ImageMagick 7.1.1-43 with extensive format support including:
- JPEG, PNG, WebP, TIFF, GIF, BMP
- RAW camera formats
- Vector formats (SVG)
- Document formats (PDF)

### Image Optimization Tools
- **jpegoptim**: JPEG compression optimization
- **optipng**: PNG lossless optimization 
- **pngquant**: PNG color quantization and palette reduction
- **gifsicle**: GIF optimization and animation tools
- **webp**: WebP format conversion and optimization (cwebp/dwebp)

## Video & Audio Processing

### FFmpeg
Complete video and audio processing suite with support for:
- **Video codecs**: H.264, H.265, VP8, VP9, AV1, and many more
- **Audio codecs**: MP3, AAC, Opus, Vorbis, FLAC
- **Container formats**: MP4, WebM, AVI, MOV, MKV, and more
- **Streaming protocols**: RTMP, HLS, DASH

**Version**: FFmpeg 7.1.1 with hardware acceleration support

Common use cases:
```bash
# Video transcoding
ffmpeg -i input.mov -c:v libx264 -c:a aac output.mp4

# Create thumbnails
ffmpeg -i video.mp4 -vf "thumbnail" -frames:v 1 thumb.jpg

# Audio extraction
ffmpeg -i video.mp4 -vn -acodec copy audio.aac
```

## Document Processing

### Ghostscript
PostScript and PDF processing capabilities:
- PDF manipulation and optimization
- PostScript rendering
- PDF/A conversion
- Page extraction and merging

**Version**: Ghostscript 10.05.1

## Build Tools & Dependencies

The images also include build tools for compiling additional multimedia libraries:
- **build-essential**: GCC, make, and build tools
- **libmagickwand-dev**: ImageMagick development headers
- **libfreetype6-dev**: Font rendering development headers
- Various codec development libraries

## PHP Extensions

### Available Extensions
- **gd**: Basic image processing (built-in PHP)
- **intl**: Internationalization support
- **pdo_mysql**: Database connectivity
- **zip**: Archive handling

### ImageMagick PHP Extension
**Note**: The ImageMagick PHP extension (imagick) is not currently available for PHP 8.4 through PECL. However, all ImageMagick CLI tools are available and can be called from PHP using `exec()`, `shell_exec()`, or `system()`.

Example PHP usage:
```php
// Using ImageMagick CLI from PHP
exec('convert input.jpg -resize 300x300 output.jpg');

// Using FFmpeg from PHP  
exec('ffmpeg -i video.mp4 -ss 00:00:10 -frames:v 1 thumbnail.jpg');

// Using optimization tools
exec('jpegoptim --max=85 image.jpg');
exec('optipng -o2 image.png');
```

## Usage Examples

### Image Processing Pipeline
```bash
# Convert and optimize images
convert original.png -resize 1200x800 -quality 85 resized.jpg
jpegoptim --max=80 resized.jpg

# Create WebP versions
cwebp -q 80 resized.jpg -o resized.webp

# Generate thumbnails
convert resized.jpg -resize 200x200^ -gravity center -crop 200x200+0+0 thumb.jpg
```

### Video Processing Pipeline
```bash
# Create web-optimized video
ffmpeg -i original.mov -c:v libx264 -preset slow -crf 23 -c:a aac -b:a 128k output.mp4

# Generate video thumbnail
ffmpeg -i output.mp4 -ss 00:00:05 -vframes 1 -q:v 2 video_thumb.jpg

# Create GIF from video
ffmpeg -i output.mp4 -vf "fps=10,scale=320:-1" -t 3 preview.gif
gifsicle -O3 --colors 64 preview.gif -o preview_optimized.gif
```

These tools enable REDAXO applications to perform sophisticated multimedia processing directly within the Docker container environment, supporting modern web development workflows with automatic image optimization, video transcoding, and document processing.