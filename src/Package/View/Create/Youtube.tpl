{{$flags = flags()}}
{{$options = options()}}
{{if(is.empty($options.request.target_dir))}}
{{$options.request.target_dir =
config('project.dir.mount') +
'Audio' +
config('ds') +
'Music' +
config('ds') +
date('W-Y') +
config('ds')}}
{{/if}}
{{if(is.empty($options.request.status.url))}}
{{$options.request.status.url = config('ramdisk.url') +
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
{{if(is.empty($options.request.status.controller))}}
{{$options.request.status.controller = 'Package:R3m:Io:Task:Controller:Status:youtube'}}
{{/if}}
{{if(!file.exist($options.request.target_dir))}}
{{dir.create($options.request.target_dir)}}
{{file.permission([
'dir_audio' => dir.name($options.request.target_dir, 2),
'dir_music' => dir.name($options.request.target_dir, 1),
'dir_target_dir' => $options.request.target_dir,
])}}
{{/if}}
{{$status.dir = dir.name($options.request.status.url)}}
{{if(!file.exist($status.dir))}}
{{dir.create($status.dir)}}
{{/if}}
{{if(is.empty($options.options.command))}}
{{$options.request.url = $options.url}}
{{$options.options.command = [
'cd ' + $options.request.target_dir + ' && yt-dlp -x --restrict-filenames --audio-format mp3 --prefer-ffmpeg ' + $options.url + '  2>&1 | tee -a ' + $options.request.status.url
]}}
{{/if}}
{{$response = Package.R3m.Io.Task:Main:create($flags, $options)}}
{{$response|object:'json'}}