{{$response = Package.Raxon.Account:Admin:admin.email.change(flags(), options())}}
{{$response|>object:'json'}}