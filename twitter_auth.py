import csv
import tweepy
from tweepy import OAuthHandler


def process_or_store(tweet):
	with open('data.csv', 'a+') as csvfile:
		try:
			content = tweet.text
			writer = csv.writer(csvfile, delimiter=',')
			writer.writerow([content, tweet.created_at, tweet.entities])
		except Exception as e:
			print e
	# with open('data.json', 'a+') as f:
	# 	json.dump(tweet, f)
    # print(json.dumps(tweet))

consumer_key = '8ZWfXa9lnj9C5B3Gc5vIx0GDW'
consumer_secret = 'venzQcxSlnbjUCskSUpj2Pg8r74twxn5lwwcb7mpLBLpEZjPxt'
access_token = '268776080-xfWMumOB4tnG74WS9RmAnPCkj7i5qezTn4VMxOTx'
access_secret = '90N8Rmtvrx1igpN8RCjDzJwXD5TVaVIXexEQosFeX8x4t'
 
auth = OAuthHandler(consumer_key, consumer_secret)
auth.set_access_token(access_token, access_secret)
 
api = tweepy.API(auth)

statuses = tweepy.Cursor(api.search, q='#GalaxyS8', lang = 'en')
# statuses = tweepy.Cursor(api.home_timeline)

for status in statuses.items():
    process_or_store(status) 
