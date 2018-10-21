#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import requests
import json
import threading

from datetime import datetime


def load_config():
    with open("config.json", "r") as f:
        config = json.load(f)
    return config


def verify_user_key(session, cmsg_url, username, key):
    request_url = "".join([cmsg_url, "?f=0&user={0}&key={1}".format(
        username, key)])
    result = session.get(request_url).json()
    return result["success"] == "1"


def create_new_user(session, cmsg_url, username):
    request_url = "".join([cmsg_url, "?f=2&user={0}".format(username)])
    result = session.get(request_url).json()
    if (result["success"] == "1"):
        return result["message"]
    else:
        return None


def get_messages(session, cmsg_url, time_from):
    request_url = "".join([cmsg_url, "?f=1&time={0}".format(time_from.strftime(
        "%Y-%m-%d %H:%M:%S"))])
    result = session.get(request_url).json()
    messages = []
    for message_details in result:
        messages.append((message_details["display_name"],
                         message_details["content"],
                         datetime.strptime(message_details["time"],
                                           "%Y-%m-%d %H:%M:%S")))
    return messages


def get_all_messages(session, cmsg_url):
    return get_messages(session, cmsg_url,
                        datetime.strptime("0001-01-01 00:00:00",
                                          "%Y-%m-%d %H:%M:%S"))


def print_message(display_name, contents, time):
    time_str = time.strftime("%H:%M")
    print("".join(["\r[", display_name, " at ", time_str, "]: ", contents]))


def print_message_list(messages):
    # in format: [(display_name, contents)]
    for message in messages:
        print_message(message[0], message[1], message[2])


def send_message(session, cmsg_url, username, key, contents):
    request_url = "".join([cmsg_url, "?f=3&user={0}&key={1}&msg={2}".format(
        username, key, contents)])
    result = session.get(request_url).json()
    return result["success"] == "1"


def fetch_and_display_messages(lock, session, cmsg_url):
    # all_msgs = get_all_messages(session, cmsg_url)
    # print_message_list(all_msgs)
    # most_recent_time = all_msgs[-1][2]

    most_recent_time = datetime.now()

    while(True):
        new_messages = get_messages(session, cmsg_url, most_recent_time)
        if (len(new_messages) > 0):
            lock.acquire()
            print_message_list(new_messages)
            print("\r\r> ", end="")
            most_recent_time = new_messages[-1][2]
            lock.release()


def parse_commands(lock, session, cmsg_url, username, key):
    running = True
    while (running):
        command = input("> ")
        print("\033[A\r\033[A")
        # lock.acquire()
        # print("".join(["\r\r", command]))
        # lock.release()
        if (command.startswith("!")):
            if (command.startswith("!mkuser ")):
                new_username = command[8:].strip().replace(" ", "")
                new_key = create_new_user(session, cmsg_url, new_username)
                if (new_key is not None):
                    lock.acquire()
                    print("\r\rCreated new user \"{0}\" with key: {1}.".format(
                        new_username, new_key))
                    lock.release()
                else:
                    lock.acquire()
                    print("\r\rFailed to create new user!")
                    lock.release()
        else:
            sent = send_message(session, cmsg_url, username, key, command)
            if (not sent):
                lock.acquire()
                print("\r\rFailed to send message!")
                lock.release()


if (__name__ == "__main__"):
    session = requests.Session()
    session.headers.update({'User-Agent': 'pycmsg'})

    # Load configuration
    config = load_config()
    cmsg_url = config["cmsg_url"]

    if (config["username"] != "" and config["key"] != ""):
        if (not verify_user_key(session, cmsg_url, config["username"],
                                config["key"])):
            print("Error: Invalid username and key configuration.")
            exit()

    # Load config json
    # Create message buffer

    lock = threading.Lock()

    msg_thread = threading.Thread(
        target=fetch_and_display_messages, args=(lock, session, cmsg_url))
    msg_thread.start()

    cmd_thread = threading.Thread(
        target=parse_commands, args=(lock, session, cmsg_url,
                                     config["username"], config["key"]))
    cmd_thread.start()
