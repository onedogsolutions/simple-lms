import json
import pathlib
import subprocess
import sys
import urllib.request

site, destination, source, max_bytes = sys.argv[1:5]
payload = {
    "path": destination,
    "expires_in": 1800,
    "max_bytes": int(max_bytes),
    "overwrite": False,
    "create_directories": True,
    "novamira_review": {
        "attention": 1,
        "summary": "Upload a verified plugin ZIP to staging for the approved plugin deployment; no plugin is activated by this upload.",
    },
}
result = subprocess.run(
    ["novamira", "--site", site, "run", "novamira/create-upload-link", "--input", json.dumps(payload), "--yes", "--json"],
    check=False,
    capture_output=True,
    text=True,
)
try:
    response = json.loads(result.stdout)
except json.JSONDecodeError:
    print(result.stdout)
    print(result.stderr, file=sys.stderr)
    raise SystemExit(result.returncode or 1)

if not response.get("ok") or "upload_url" not in response.get("data", {}):
    print(json.dumps(response, separators=(",", ":")))
    raise SystemExit(2)

data = response["data"]
body = pathlib.Path(source).read_bytes()
request = urllib.request.Request(
    data["upload_url"],
    data=body,
    method=data["method"],
    headers={data["token_header"]: data["upload_token"]},
)
with urllib.request.urlopen(request, timeout=120) as uploaded:
    print(json.dumps({"status": uploaded.status, "path": data["path"], "bytes": len(body), "response": uploaded.read().decode("utf-8", "replace")}))
