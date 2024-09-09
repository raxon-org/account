{{R3M}}
{{$response = Package.Raxon.Account:Main:account.create.jwt(flags(), options())}}
{{$response|object:'json'}}