{{R3M}}
{{$response = Package.Raxon.Account:Main:user.create(flags(), options())}}
{{$response|object:'json'}}