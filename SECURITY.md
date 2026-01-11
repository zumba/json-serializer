Hello @williamdes,

Thank you for the feedback. I have corrected the Supported Versions table to align with the actual project releases (v3.2.2,v3.2.1) as requested.

The reason for this SECURITY.md proposal is that I have discovered a Critical Remote Code Execution (RCE) vulnerability in the library. It is an Insecure Deserialization (CWE-502) flaw that allows an unauthenticated attacker to execute arbitrary system commands by leveraging the @type property.

I have a detailed technical report and a Proof of Concept (PoC) ready to share. Please let me know the preferred secure channel to disclose the full details, or consider enabling GitHub Private Vulnerability Reporting so I can submit it here privately.

I would also like to assist in coordinating a fix and assigning a CVE ID for this issue.

Best regards, @TheDeepOpc
