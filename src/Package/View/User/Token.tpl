{{R3M}}
{{$response = Package.Raxon.Org.Account:Main:user.token(flags(), options())}}
{{$response|object:'json'}}