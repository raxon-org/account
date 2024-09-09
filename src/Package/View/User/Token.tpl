{{R3M}}
{{$response = Package.Raxon.Account:Main:user.token(flags(), options())}}
{{$response|object:'json'}}