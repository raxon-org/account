{{R3M}}
{{$response = Package.Raxon.Account:Main:setup.jwt(flags(), options())}}
{{$response|object:'json'}}