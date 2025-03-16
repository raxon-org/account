{{$response = Package.Raxon.Account:User:user.read(flags(), options())}}
{{$response|object:'json'}}
/**
user with profile ?string instead of string (so allow null)
*/