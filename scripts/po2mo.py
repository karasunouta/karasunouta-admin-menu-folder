#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
PO to MO Compiler Utility
Compiles standard gettext .po files into .mo binary files.
"""

import struct
import sys
import re
import os

def parse_po(po_path):
    with open(po_path, 'r', encoding='utf-8') as f:
        content = f.read()

    lines = content.splitlines()
    entries = []
    current_msgid = None
    current_msgstr = None
    state = None

    for line in lines:
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        
        if line.startswith('msgid '):
            if current_msgid is not None and current_msgstr is not None:
                entries.append((current_msgid, current_msgstr))
            first_quote = line.find('"')
            last_quote = line.rfind('"')
            current_msgid = line[first_quote+1:last_quote] if first_quote != -1 else ""
            current_msgstr = ""
            state = 'msgid'
        elif line.startswith('msgstr '):
            first_quote = line.find('"')
            last_quote = line.rfind('"')
            current_msgstr = line[first_quote+1:last_quote] if first_quote != -1 else ""
            state = 'msgstr'
        elif line.startswith('"') and line.endswith('"'):
            val = line[1:-1]
            if state == 'msgid':
                current_msgid += val
            elif state == 'msgstr':
                current_msgstr += val

    if current_msgid is not None and current_msgstr is not None:
        entries.append((current_msgid, current_msgstr))

    # Helper to unescape strings
    def unescape(s):
        import codecs
        return codecs.escape_decode(s.encode('utf-8'))[0].decode('utf-8')

    unescaped_entries = []
    for msgid, msgstr in entries:
        # Keep empty msgid (the header), but filter out other untranslated entries
        if msgid == "" or msgstr != "":
            unescaped_entries.append((unescape(msgid), unescape(msgstr)))

    return unescaped_entries

def write_mo(entries, mo_path):
    # Sort entries by msgid (bytes) as required by gettext specification for binary search
    entries.sort(key=lambda x: x[0].encode('utf-8'))

    keystr = b''
    valstr = b''
    keyidx = []
    validx = []

    for key, val in entries:
        k_bytes = key.encode('utf-8')
        v_bytes = val.encode('utf-8')
        
        keyidx.append((len(k_bytes), k_bytes))
        validx.append((len(v_bytes), v_bytes))

    n_strings = len(entries)
    keys_table_offset = 28
    vals_table_offset = keys_table_offset + (n_strings * 8)
    strings_offset = vals_table_offset + (n_strings * 8)

    key_offsets = []
    val_offsets = []
    
    current_str_offset = strings_offset
    for length, k_bytes in keyidx:
        key_offsets.append((length, current_str_offset))
        current_str_offset += length + 1 # +1 for null terminator
        
    for length, v_bytes in validx:
        val_offsets.append((length, current_str_offset))
        current_str_offset += length + 1 # +1 for null terminator

    magic = 0x950412de
    revision = 0
    
    header = struct.pack('<I I I I I I I', magic, revision, n_strings, keys_table_offset, vals_table_offset, 0, 0)
    
    keys_table = b''
    for length, offset in key_offsets:
        keys_table += struct.pack('<I I', length, offset)
        
    vals_table = b''
    for length, offset in val_offsets:
        vals_table += struct.pack('<I I', length, offset)
        
    strings_data = b''
    for _, k_bytes in keyidx:
        strings_data += k_bytes + b'\x00'
    for _, v_bytes in validx:
        strings_data += v_bytes + b'\x00'

    with open(mo_path, 'wb') as f:
        f.write(header)
        f.write(keys_table)
        f.write(vals_table)
        f.write(strings_data)

def compile_po_to_mo(po_path, mo_path):
    entries = parse_po(po_path)
    write_mo(entries, mo_path)
    print(f"Compiled {po_path} -> {mo_path} ({len(entries)} strings)")

if __name__ == '__main__':
    if len(sys.argv) >= 3:
        compile_po_to_mo(sys.argv[1], sys.argv[2])
    else:
        # Auto-compile all .po files in languages directory
        script_dir = os.path.dirname(os.path.abspath(__file__))
        lang_dirs = [
            os.path.join(script_dir, '..', 'languages'),
            os.path.join(os.getcwd(), 'languages')
        ]
        compiled_count = 0
        for lang_dir in lang_dirs:
            if os.path.isdir(lang_dir):
                po_files = [f for f in os.listdir(lang_dir) if f.endswith('.po')]
                for po_file in po_files:
                    po_path = os.path.join(lang_dir, po_file)
                    mo_path = os.path.join(lang_dir, po_file[:-3] + '.mo')
                    compile_po_to_mo(po_path, mo_path)
                    compiled_count += 1
                if compiled_count > 0:
                    break
        if compiled_count == 0:
            print("No .po files found to compile.")
