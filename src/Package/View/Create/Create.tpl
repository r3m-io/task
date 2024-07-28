{{R3M}}
{{$response = Package.R3m.Io.Task:Main:create(flags(), options())}}
{{$response|object:'json'}}
/*
cd ' . $target_directory . ' &&
yt-dlp -x --restrict-filenames --audio-format mp3 --prefer-ffmpeg ' . $object->request('node.url') . '  2>&1 | tee -a ' . $url . '
*/