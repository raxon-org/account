{{$response = Package.Raxon.Account:User:user.create(flags(), options())}}
{{$response|object:'json'}}