{{R3M}}
{{$response = Package.Raxon.Account:Main:setup.admin(flags(), options())}}
{{$response|object:'json'}}