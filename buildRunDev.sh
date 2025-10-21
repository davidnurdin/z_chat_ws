docker build -t chatwsdev . && docker run --name chatwsdev --rm -it -v .:/app/ --network host chatwsdev

