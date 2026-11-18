# Embed providers

<!-- cspell:ignore Animoto Crowdsignal Dailymotion imdb Issuu Kickstarter Mixcloud reverbnation Scribd slideshare smugmug soundcloud uamswp videopress -->

Written against v1.3.1.

The providers the *Embed Variations* section of the settings page lists, from `uamswp_block_remover_known_embed_variations()` in [`uamswp-block-remover.php`](https://github.com/UAMS-Web/uamswp-block-remover/blob/main/uamswp-block-remover.php). The variation name is what is stored in `blocked_embeds` (see [Settings](Settings.md)); a save accepts no other name. None is hidden by default.

The list is maintained by hand. When WordPress adds a provider, it appears in the editor but not on the settings page until this list is updated; see [Hiding embed providers](Hiding-embed-providers.md).

| Provider | Variation name |
| --- | --- |
| Amazon Kindle | `amazon-kindle` |
| Animoto | `animoto` |
| Cloudup | `cloudup` |
| Crowdsignal | `crowdsignal` |
| Dailymotion | `dailymotion` |
| Flickr | `flickr` |
| IMDb | `imdb` |
| Imgur | `imgur` |
| Instagram | `instagram` |
| Issuu | `issuu` |
| Kickstarter | `kickstarter` |
| Mixcloud | `mixcloud` |
| Pinterest | `pinterest` |
| Pocket Casts | `pocket-casts` |
| Reddit | `reddit` |
| ReverbNation | `reverbnation` |
| Screencast | `screencast` |
| Scribd | `scribd` |
| SlideShare | `slideshare` |
| SmugMug | `smugmug` |
| SoundCloud | `soundcloud` |
| Speaker Deck | `speaker-deck` |
| Spotify | `spotify` |
| TED | `ted` |
| TikTok | `tiktok` |
| Tumblr | `tumblr` |
| X (Twitter) | `twitter` |
| VideoPress | `videopress` |
| Vimeo | `vimeo` |
| Wolfram Cloud | `wolfram-cloud` |
| WordPress | `wordpress` |
| WordPress.tv | `wordpress-tv` |
| YouTube | `youtube` |
