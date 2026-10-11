"""Encrypt/decrypt CRM snapshots using AES-256-GCM; keep the key outside Git."""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path

from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes

MAGIC = b"CRM-SNAPSHOT-AES256GCM-v1\n"
CHUNK_SIZE = 1024 * 1024


def transform(mode: str, source: Path, destination: Path, key_file: Path) -> None:
    if not source.is_file() or destination.exists() or source.resolve() == destination.resolve():
        raise ValueError("Input missing or destination already exists; no files overwritten.")
    key = bytes.fromhex(key_file.read_text(encoding="ascii").strip())
    if len(key) != 32:
        raise ValueError("Key must contain exactly 32 bytes encoded as 64 hex characters.")
    temporary = destination.with_name(destination.name + ".part")
    if temporary.exists():
        raise ValueError("Partial output already exists; inspect it before retrying.")
    destination.parent.mkdir(parents=True, exist_ok=True)
    try:
        with source.open("rb") as reader, temporary.open("xb") as writer:
            if mode == "encrypt":
                nonce = os.urandom(12)
                context = Cipher(algorithms.AES(key), modes.GCM(nonce)).encryptor()
                context.authenticate_additional_data(MAGIC)
                writer.write(MAGIC + nonce)
                while chunk := reader.read(CHUNK_SIZE):
                    writer.write(context.update(chunk))
                writer.write(context.finalize())
                writer.write(context.tag)
            else:
                if reader.read(len(MAGIC)) != MAGIC:
                    raise ValueError("Unsupported snapshot format.")
                nonce = reader.read(12)
                payload_start = reader.tell()
                size = source.stat().st_size
                if len(nonce) != 12 or size < payload_start + 16:
                    raise ValueError("Truncated snapshot.")
                reader.seek(-16, os.SEEK_END)
                tag = reader.read(16)
                reader.seek(payload_start)
                context = Cipher(algorithms.AES(key), modes.GCM(nonce, tag)).decryptor()
                context.authenticate_additional_data(MAGIC)
                remaining = size - payload_start - 16
                while remaining:
                    chunk = reader.read(min(CHUNK_SIZE, remaining))
                    if not chunk:
                        raise ValueError("Truncated encrypted payload.")
                    remaining -= len(chunk)
                    writer.write(context.update(chunk))
                writer.write(context.finalize())
        temporary.replace(destination)
    except BaseException:
        if temporary.exists():
            temporary.unlink()
        raise
    digest = hashlib.file_digest(destination.open("rb"), "sha256").hexdigest()
    print(json.dumps({"mode": mode, "output": str(destination), "bytes": destination.stat().st_size, "sha256": digest}))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("mode", choices=("encrypt", "decrypt"))
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path)
    parser.add_argument("--key-file", required=True, type=Path)
    args = parser.parse_args()
    transform(args.mode, args.source, args.destination, args.key_file)
