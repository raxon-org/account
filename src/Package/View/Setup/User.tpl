{{R3M}}
{{$response = Package.Raxon.Account:Main:setup.user(flags(), options())}}
{{$response|object:'json'}}