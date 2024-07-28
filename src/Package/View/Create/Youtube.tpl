{{d(config('volume'))}}
{{dd(config('project.dir'))}}
{{$flags = flags()}}
{{$options = options()}}
{{if(is.empty($options.target_dir))
{{$options.target_dir =
config('project.dir.mount') +
'Audio' +
config('ds') +
'Music' +
config('ds') +
date('W-Y') +
config('ds')}}
{{/if}}
{{if(is.empty($options.status.url))}}
{{$options.status.url = config('ramdisk.url') +
config('posix.id') +
config('ds') +
'Application' +
config('ds') +
'Youtube' +
config('ds') +
uuid() +
config('extension.log')
}}
{{/if}}
{{if(!File::exist($options.target_dir))}}
{{dir.create($options.target_dir)}}
{{/if}}
{{$status.dir = dir.name($options.status.url)}}
{{if(!File::exist($status.dir))}}
{{dir.create($status.dir)}}
{{/if}}
{{if(is.empty($options.command))}}
{{$options.command = [
'cd ' + $options.target_dir + ' && yt-dlp -x --restrict-filenames --audio-format mp3 --prefer-ffmpeg ' + $options.url + '  2>&1 | tee -a ' + $options.status.url
]}}
{{/if}}
{{$response = Package.R3m.Io.Task:Main:create($flags, $options)}}
{{$response|object:'json'}}