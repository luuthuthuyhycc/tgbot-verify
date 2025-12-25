#!/usr/bin/env python3
"""
IMAP Email Reader - Python wrapper for environments without PHP IMAP extension
Usage: python3 imap_reader.py <imap_server> <username> <password> <to_email> [timeout]
Returns JSON with email data and parsed emailToken
"""

import sys
import json
import imaplib
import email
from email.header import decode_header
import re
import time

def main():
    if len(sys.argv) < 5:
        print(json.dumps({
            "success": False,
            "error": "Usage: imap_reader.py <imap_server> <username> <password> <to_email> [timeout]"
        }))
        sys.exit(1)
    
    imap_server = sys.argv[1]
    username = sys.argv[2]
    password = sys.argv[3]
    to_email = sys.argv[4]
    timeout = int(sys.argv[5]) if len(sys.argv) > 5 else 120
    
    # Parse IMAP server (format: {host:port/imap/ssl}INBOX)
    host = "imap.gmail.com"
    port = 993
    use_ssl = True
    
    if imap_server.startswith("{"):
        match = re.match(r'\{([^:]+):(\d+)(/[^}]*)?\}', imap_server)
        if match:
            host = match.group(1)
            port = int(match.group(2))
            flags = match.group(3) or ""
            use_ssl = "/ssl" in flags.lower()
    
    try:
        # Connect
        if use_ssl:
            mail = imaplib.IMAP4_SSL(host, port)
        else:
            mail = imaplib.IMAP4(host, port)
        
        mail.login(username, password)
        mail.select("INBOX")
        
        # Wait and search for email
        start_time = time.time()
        email_data = None
        
        while time.time() - start_time < timeout:
            mail.noop()  # Refresh
            
            # Search for SheerID email to specific address
            status, messages = mail.search(None, f'TO "{to_email}" FROM "sheerid"')
            
            if status == "OK" and messages[0]:
                ids = messages[0].split()
                latest_id = ids[-1]
                
                status, data = mail.fetch(latest_id, "(RFC822)")
                if status == "OK":
                    msg = email.message_from_bytes(data[0][1])
                    
                    # Get subject
                    subject = msg.get("Subject", "")
                    if subject:
                        decoded = decode_header(subject)
                        subject = decoded[0][0]
                        if isinstance(subject, bytes):
                            subject = subject.decode("utf-8", errors="ignore")
                    
                    # Get body
                    body = ""
                    if msg.is_multipart():
                        for part in msg.walk():
                            content_type = part.get_content_type()
                            if content_type in ["text/plain", "text/html"]:
                                try:
                                    payload = part.get_payload(decode=True)
                                    if payload:
                                        decoded_body = payload.decode("utf-8", errors="ignore")
                                        if "emailToken" in decoded_body:
                                            body = decoded_body
                                            break
                                        if not body:
                                            body = decoded_body
                                except:
                                    pass
                    else:
                        try:
                            body = msg.get_payload(decode=True).decode("utf-8", errors="ignore")
                        except:
                            pass
                    
                    # Parse emailToken
                    email_token = None
                    match = re.search(r'emailToken=([a-zA-Z0-9_-]+)', body)
                    if match:
                        email_token = match.group(1)
                    
                    email_data = {
                        "success": True,
                        "email": {
                            "subject": subject,
                            "from": msg.get("From", ""),
                            "to": msg.get("To", ""),
                            "date": msg.get("Date", ""),
                            "body_length": len(body),
                            "body": body
                        },
                        "emailToken": email_token
                    }
                    break
            
            time.sleep(5)
        
        mail.logout()
        
        if email_data:
            print(json.dumps(email_data, ensure_ascii=False))
        else:
            print(json.dumps({
                "success": False,
                "error": f"No SheerID email found for {to_email} within {timeout}s"
            }))
            
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()
