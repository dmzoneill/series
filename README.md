# series

A PHP command-line tool for keeping a TV series library up to date.

## What it does

- Scans the local series folders and reads file details with MediaInfo.
- Looks up series, seasons and episodes on TheTVDB.
- Finds missing episodes on EZTV.
- Sends downloads to qBittorrent through its web API.
- Uses worker threads to fetch TVDB and EZTV pages and media details in
  parallel. Downloaded data is cached in `tvdb` and `eztv`.

## Tech

- PHP CLI with the `pthreads` extension
- TheTVDB, EZTV, qBittorrent and MediaInfo

## Running

Install PHP with `pthreads` (thread-safe build) and MediaInfo, set the paths and
qBittorrent address in the scripts, then run:

```sh
php series.php
```

## License

Licensed under the Apache License 2.0. See [LICENSE](LICENSE).
