{{$response = Package.Raxon.Account:User:user.read(flags(), options())}}
{{$response|object:'json'}}