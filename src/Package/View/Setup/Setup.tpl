{{R3M}}
{{$register = Package.R3m.Io.Account:Init:register()}}
{{if(!is.empty($register))}}
{{Package.R3m.Io.Account:Import:role.system()}}
{{/if}}