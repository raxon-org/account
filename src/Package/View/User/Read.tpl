{{$response = Package.Raxon.Account:Main:user.read(flags(), options())}}
{{$response|object:'json'}}