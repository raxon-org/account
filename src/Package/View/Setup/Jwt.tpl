{{R3M}}
{{$response = Package.Raxon.Org.Account:Main:setup.jwt(flags(), options())}}
{{$response|object:'json'}}