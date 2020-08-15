vcl 4.1;
import cookie;

backend default {
  .host = "nginx";
}

sub vcl_recv {
  cookie.parse(req.http.Cookie);
  set req.http.X-Atom-Culture = cookie.get("atom_culture");
  unset req.http.Cookie;

  if (req.url ~ "sf_culture") {
    return(pass);
  }

  return (hash);
}

sub vcl_hash {
  hash_data(req.http.X-Atom-Culture);
}

sub vcl_backend_response {
  if (bereq.url !~ "sf_culture")  {
    unset beresp.http.Set-Cookie;
  }

  return (deliver);
}

sub vcl_deliver {
  if (obj.hits > 0) {
    set resp.http.X-Cache = "HIT";
    set resp.http.X-Cache-Hits = obj.hits;
  } else {
    set resp.http.X-Cache = "MISS";
  }
  return (deliver);
}
