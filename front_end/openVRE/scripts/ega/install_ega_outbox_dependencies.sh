#!/bin/bash

echo "installing dependencies"
apt update
apt -y install autoconf automake bzip2 ca-certificates cmake curl gcc-10 git jq libatlas-base-dev libc6-dev libedit-dev libglib2.0-dev libssl-dev libtool make ninja-build pkg-config python3 python3-pip samtools sshfs tabix udev zlib1g-dev

gcc --version

pip install -U pip
pip install meson

echo "installing libfuse"

git clone https://github.com/libfuse/libfuse.git
cd libfuse
git checkout fuse-3.16.2
mkdir build
cd build
meson ..
ninja
ninja install

echo "installing crypt4ghfs"

pip install --upgrade pip wheel
ln -s $(which pip) /usr/bin/pip
pip install git+https://github.com/inab/crypt4ghfs.git@v1.2.6

#echo "user_allow_other" >> /usr/local/etc/fuse.conf
echo "user_allow_other" >> /etc/fuse.conf

mkdir -p /encrypted_files && chown application:application /encrypted_files
mkdir -p /clean_files && chown application:application /clean_files
