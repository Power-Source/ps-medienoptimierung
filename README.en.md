# PS Medienoptimierung

[Deutsch](README.md) | **English**

[![Version](https://img.shields.io/badge/Version-1.0.2-2271b1?style=flat-square)](readme.txt)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-up%20to%207.1.4-21759b?style=flat-square&logo=wordpress&logoColor=white)
![ClassicPress](https://img.shields.io/badge/ClassicPress-2.7.4-03768e?style=flat-square)
[![License](https://img.shields.io/badge/License-GPL--3.0--or--later-2ea44f?style=flat-square)](https://www.gnu.org/licenses/gpl-3.0.html)

### Less bulk. More speed. Images and videos in one place.

Optimize your images, shrink large videos, and prepare MP4 files for mobile devices — right in your WordPress or ClassicPress media administration.

**On your own server. Without an external optimization service. Without separate settings pages.**

---

## What you get

| For your images | For your videos |
| --- | --- |
| Optimize JPG, PNG, and GIF files | Prepare MP4 videos for mobile devices |
| Automatically optimize new uploads | Optionally convert new uploads automatically |
| Process existing images in bulk | Optimize existing videos in bulk |
| Optionally resize large original images | Optionally limit resolution while preserving proportions |
| Keep EXIF data if you want to | Reduce file size through compression |
| Optimize additional image folders | Detect suitable codecs and recheck existing videos |

The overview shows what is already done, what still needs attention, and how much storage space optimization has saved. For videos, it also shows errors and any increases in file size.

## Get started in a few steps

1. Install and activate **PS Medienoptimierung** through your plugin administration.
2. Open **Media → Media Optimization**.
3. Adjust the image and video options in the shared settings form and save your changes.
4. Start the relevant **bulk optimization** to process existing media.

You can enable automatic processing of new uploads independently for images and videos.

> **Before your first large optimization run:** We recommend making a current backup of your media. Video conversion is lossy and cannot be reversed without a backup of the original.

## Images: smaller files, faster loading

Optimize individual images or work through your existing media library in bulk. If you wish, the plugin can process new uploads automatically and limit the dimensions of large original images.

You also decide whether to retain EXIF data and whether original images should be optimized or backed up. **Folder optimization** lets you include images outside the regular media library.

## Videos: suitable codecs instead of unpleasant surprises

An MP4 extension alone does not tell you whether a video will play well on a phone. The way the video and audio are encoded matters too.

When converting a video, PS Medienoptimierung uses:

- **MP4 with H.264** for the video.
- **8-bit color and 4:2:0 chroma subsampling** for broad device compatibility.
- **AAC stereo** when an audio track is present.
- **30 frames per second** for the output.

Silent videos stay silent. Existing video URLs and filenames are preserved.

### Check first, then optimize

Use **Recheck videos** to identify compatible videos and see what still needs optimization, without changing the files.

**Video bulk optimization** then processes the remaining videos one at a time. You can see progress, results, errors, and file sizes before and after processing. Videos already processed with the same settings are not compressed again on every run.

Keep the page open during bulk processing. **Stop after the current video** ends the run once the video being processed has finished.

### You control quality and size

| Video option | Default | What it does |
| --- | --- | --- |
| Automatic video optimization | Off | Processes new video uploads in the background. |
| Video compression | On | Attempts to reduce the file size of videos that already have compatible codecs. |
| Video quality | 23 | Lower values mean higher quality and usually larger files. Adjustable from 18 to 30. |
| Limit video dimensions | Off | Scales large videos down proportionally to fit your maximum width and height. |
| Original backup | Off | Optionally keeps an additional copy of the original video. |

A size limit such as **1280 × 720 pixels** can save additional storage space and bandwidth for large videos. Smaller videos are not upscaled.

**Important:** A smaller file is not always possible. If a video is already compatible and does not need resizing, the original is kept when compression provides no size benefit. A necessary codec conversion or resize may produce a larger file instead — the plugin shows this increase.

### Verified before replacement

The new video file is created separately first. Its format, duration, audio, and ability to be decoded completely are checked before it replaces the original.

Optional original backups are **disabled by default** to save storage space. When enabled, the first original file is kept as an additional copy. Without a backup, a successfully replaced file cannot be restored.

## See everything at a glance

The video section shows:

- The number of **mobile-compatible videos**.
- Videos still awaiting optimization or checking.
- Videos already optimized or resized.
- Errors and unsupported files.
- **Storage savings and percentages**, along with file sizes before and after processing.

Under **Results and errors → Clear log**, you can permanently clear the displayed results list. Optimization records, savings, and unresolved errors in the statistics are preserved. New messages will appear in the list again.

## One place for everything — including multisite

Image and video options live on the same media optimization page and use the same save button.

On a multisite installation, you can manage settings centrally through the network administration. When central control is enabled, subsites inherit those settings. Otherwise, each site manages its own settings.

Bulk processing stays on the individual site and processes that site's media.

## German or English

The interface follows your website or user profile language. German and English (US and UK) are supported, including settings, messages, and video processing.

Choose the website language under **Settings → General**. To change only your own administration language, use **Users → Profile**. No separate language switch in the plugin is needed.

## What your hosting needs

For images, the plugin uses your server's image-processing capabilities. For videos, **FFmpeg with ffprobe or GStreamer** must be available with suitable H.264 and AAC support.

You do not need to figure this out yourself: the video section displays the detected encoder or an understandable notice. If support is missing, ask your hosting provider to set it up. **Image optimization continues to work independently.**

FFmpeg is not bundled with the plugin. Your server also needs enough free storage space and processing time for larger videos.

## Good to know

**Which videos can be processed?**  
Currently, local files with an **.mp4 extension** in your site's uploads directory. MOV, WebM, AVI, and offloaded videos, such as those stored on S3, are left unchanged with an explanatory notice.

**Does the page need to stay open for automatic upload processing?**  
No. Automatic processing uses your site's background tasks. On sites with very little traffic, processing may start later; your hosting provider can arrange regular execution.

**Why do I still see the old version after conversion?**  
Because the video URL stays the same, an older copy may remain in your browser or website cache. Clear your CDN cache too, if applicable.

**Are original backups deleted when I turn the option off?**  
No. Existing backups are kept. Deleting the associated media item also removes its registered video backup.

---

**PS Medienoptimierung** · A plugin by [PSOURCE](https://psource.eimen.net/psource/ps-medienoptimierung/)
