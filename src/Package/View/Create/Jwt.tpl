{{R3M}}
{{$response = Package.Raxon.Org.Account:Main:account.create.jwt(flags(), options())}}
{{$response|object:'json'}}