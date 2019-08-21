## Setting up SSH agent forwarding


Ensure that your own SSH key is set up and working. You can use [our guide on generating SSH keys](https://help.github.com/articles/generating-ssh-keys) if you've not done this yet.

You can test that your local key works by entering `ssh -T git@github.com` in the terminal:

```bash
ssh -T git@github.com
# Attempt to SSH in to github
Hi username! You\'ve successfully authenticated, but GitHub does not provide
shell access.
```

We're off to a great start. Let\'s set up SSH to allow agent forwarding to your server.

1. Using your favorite text editor, open up the file at `~/.ssh/config`. If this file doesn't exist, you can create it by entering `touch ~/.ssh/config` in the terminal.
2. Enter the following text into the file, replacing example.com with your server's domain name or IP:

```txt
Host example.com
  ForwardAgent yes
```
Replace example.com with your domain or its IP address.


You can check that your key is visible to ssh-agent by running the following command:

```bash
ssh-add -L
```

If the command says that no identity is available, you'll need to add your key:

```bash
#Adding your SSH key to the ssh-agent
ssh-add ~/.ssh/id_rsa
```

Note, on Linux, you will need to add this to your profile:

```bash
eval `ssh-agent`
ssh-add -k
```

### Testing SSH agent forwarding

To test that agent forwarding is working with your server, you can SSH into your server and run `ssh -T git@github.com`once more. If all is well, you'll get back the same prompt as you did locally.

If you're unsure if your local key is being used, you can also inspect the `SSH_AUTH_SOCK` variable on your server:

```bash
echo "$SSH_AUTH_SOCK"
```

If the variable is not set, it means that agent forwarding is not working

```bash
echo "$SSH_AUTH_SOCK"
# Print out the SSH_AUTH_SOCK variable
[No output]
ssh -T git@github.com
# Try to SSH to github
Permission denied (publickey).
```

### Links
- [Github Using SSH agent forwarding](https://developer.github.com/v3/guides/using-ssh-agent-forwarding/#setting-up-ssh-agent-forwarding)
- [Setup SSH key for local dev box and use agent forwarding for servers](https://github.com/mhulse/mhulse.github.io/wiki/Setup-SSH-key-for-local-dev-box-and-use-agent-forwarding-for-servers)
